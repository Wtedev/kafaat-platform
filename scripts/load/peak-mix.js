import http, { CookieJar } from 'k6/http';
import { check } from 'k6';
import { SharedArray } from 'k6/data';
import exec from 'k6/execution';

const identities = new SharedArray('identities', () => JSON.parse(open('./identities.json')));
const logins = new SharedArray('logins', () => JSON.parse(open('./logins.json')));
const target = JSON.parse(open('./peak-target.json'));
const base = __ENV.BASE_URL || target.base;

export const options = {
  scenarios: {
    peak: {
      executor: 'constant-arrival-rate',
      rate: 5,
      timeUnit: '1s',
      duration: '5m',
      preAllocatedVUs: 80,
      maxVUs: 200,
    },
  },
  thresholds: {
    http_req_failed: ['rate==0'],
    'http_req_duration{kind:page}': ['p(95)<1000'],
  },
  summaryTrendStats: ['avg', 'med', 'p(95)', 'p(99)', 'count'],
};

function forwardedFor(iteration) {
  return `10.9.${Math.floor(iteration / 200) % 250}.${iteration % 250}`;
}

function isolated(iteration, kind) {
  return {
    jar: new CookieJar(),
    tags: { kind },
    headers: { 'X-Forwarded-For': forwardedFor(iteration) },
    redirects: 0,
  };
}

export default function () {
  const iteration = exec.scenario.iterationInTest;
  const roll = iteration % 100;

  if (roll < 60) {
    openProgram(iteration);
    return;
  }

  if (roll < 85) {
    checkIn(iteration);
    return;
  }

  signIn(iteration);
}

function openProgram(iteration) {
  const response = http.get(`${base}${target.program_path}`, isolated(iteration, 'page'));
  check(response, { 'program 200': (page) => page.status === 200 });
}

function checkIn(iteration) {
  const identity = identities[iteration % identities.length];
  const params = isolated(iteration, 'attendance');
  const page = http.get(target.attendance_url, { ...params, tags: { kind: 'page' } });
  const match = String(page.body).match(/name="_token" value="([^"]+)"/);
  const identify = http.post(`${target.attendance_url}/identify`, {
    _token: match ? match[1] : '',
    national_id: identity,
    turnstile_token: 'loadtest-turnstile',
  }, params);
  const confirmPage = http.get(target.attendance_url, params);
  const confirmMatch = String(confirmPage.body).match(/name="_token" value="([^"]+)"/);
  const confirm = http.post(`${target.attendance_url}/confirm`, {
    _token: confirmMatch ? confirmMatch[1] : '',
  }, { ...params, redirects: 5 });

  check(page, { 'attendance page 200': (response) => response.status === 200 });
  check(identify, { 'identify redirects': (response) => response.status === 302 });
  check(confirm, {
    'attendance recorded': (response) => response.status === 200 && String(response.body).includes('تم تسجيل حضورك'),
  });
}

function signIn(iteration) {
  const login = logins[iteration % logins.length];
  const params = isolated(iteration, 'login');
  const page = http.get(`${base}/login`, { ...params, tags: { kind: 'page' } });
  const match = String(page.body).match(/name="_token" value="([^"]+)"/);
  const posted = http.post(`${base}/login`, {
    _token: match ? match[1] : '',
    email: login.email,
    password: login.password,
  }, params);

  check(page, { 'login page 200': (response) => response.status === 200 });
  check(posted, { 'login redirects': (response) => response.status === 302 });
}
