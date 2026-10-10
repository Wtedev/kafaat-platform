import http from 'k6/http';
import { check } from 'k6';
import { SharedArray } from 'k6/data';
import exec from 'k6/execution';

const identities = new SharedArray('identities', () => JSON.parse(open('./identities.json')));
const base = __ENV.ATTENDANCE_URL;

export const options = {
  scenarios: {
    checkin: {
      executor: 'constant-arrival-rate',
      rate: 5,
      timeUnit: '1s',
      duration: '5m',
      preAllocatedVUs: 40,
      maxVUs: 120,
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],
    checks: ['rate>0.99'],
  },
};

export default function () {
  const iteration = exec.scenario.iterationInTest;
  const identity = identities[iteration];
  if (!identity) {
    return;
  }

  const params = {
    headers: {
      'X-Forwarded-For': `10.8.${Math.floor(iteration / 20) % 250}.${iteration % 250}`,
    },
  };

  const page = http.get(base, params);
  const match = String(page.body).match(/name="_token" value="([^"]+)"/);
  const token = match ? match[1] : '';
  const identify = http.post(`${base}/identify`, {
    _token: token,
    national_id: identity,
    turnstile_token: 'test-turnstile',
  }, { ...params, redirects: 0 });

  const confirmPage = http.get(base, params);
  const confirmMatch = String(confirmPage.body).match(/name="_token" value="([^"]+)"/);
  const confirmToken = confirmMatch ? confirmMatch[1] : '';
  const confirm = http.post(`${base}/confirm`, {
    _token: confirmToken,
  }, params);

  check(page, { 'page ok': (response) => response.status === 200 });
  check(identify, { 'identify redirects': (response) => response.status === 302 });
  check(confirm, { 'attendance recorded': (response) => response.status === 200 && String(response.body).includes('تم تسجيل حضورك') });
}
