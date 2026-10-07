(function () {
    const root = document.querySelector("[data-sui-shell]");
    if (!root) return;

    const sidebarKey = "sui-sidebar-collapsed";

    function refreshIcons() {
        if (window.lucide) window.lucide.createIcons();
    }

    if (localStorage.getItem(sidebarKey) === "1" && window.innerWidth > 960) {
        root.classList.add("is-collapsed");
    }

    root.querySelector("[data-sui-collapse]")?.addEventListener("click", () => {
        if (window.innerWidth <= 960) return;
        root.classList.toggle("is-collapsed");
        localStorage.setItem(sidebarKey, root.classList.contains("is-collapsed") ? "1" : "0");
    });

    root.querySelector("[data-sui-drawer]")?.addEventListener("click", () => {
        root.classList.add("is-drawer");
    });

    root.querySelector("[data-sui-backdrop]")?.addEventListener("click", () => {
        root.classList.remove("is-drawer");
    });

    function closeMenus(except) {
        root.querySelectorAll("[data-sui-dropdown]").forEach((menu) => {
            if (menu === except) return;
            const panel = menu.querySelector(".sui-dropdown__menu");
            const trigger = menu.querySelector("[data-sui-dropdown-trigger]");
            panel?.setAttribute("hidden", "");
            trigger?.setAttribute("aria-expanded", "false");
        });
    }

    root.querySelectorAll("[data-sui-dropdown]").forEach((menu) => {
        const trigger = menu.querySelector("[data-sui-dropdown-trigger]");
        const panel = menu.querySelector(".sui-dropdown__menu");
        trigger?.addEventListener("click", (event) => {
            event.stopPropagation();
            const open = panel?.hasAttribute("hidden");
            closeMenus(menu);
            if (open) {
                panel.removeAttribute("hidden");
                trigger.setAttribute("aria-expanded", "true");
            } else {
                panel?.setAttribute("hidden", "");
                trigger.setAttribute("aria-expanded", "false");
            }
        });
        panel?.addEventListener("click", (event) => event.stopPropagation());
    });

    document.addEventListener("click", () => closeMenus(null));
    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") {
            closeMenus(null);
            root.querySelectorAll("[data-sui-modal]").forEach((modal) => modal.setAttribute("hidden", ""));
            root.classList.remove("is-drawer");
        }
    });

    function toast(tone, title, body) {
        const stack = document.querySelector("[data-sui-toasts]");
        if (!stack) return;
        const item = document.createElement("div");
        item.className = "sui-toast sui-toast--" + (tone || "info");
        item.innerHTML = "<div><strong></strong><span></span></div>";
        item.querySelector("strong").textContent = title;
        item.querySelector("span").textContent = body || "";
        stack.appendChild(item);
        window.setTimeout(() => item.remove(), 3200);
    }

    window.suiToast = toast;

    document.querySelectorAll("[data-sui-flash]").forEach((node) => {
        toast(node.dataset.tone || "info", node.dataset.title || "تم", node.dataset.body || "");
    });

    const roleNotes = {
        admin: "وصول كامل لإدارة المنصة والموظفين والأدوار.",
        staff: "صلاحيات العمل اليومية الممنوحة لهذا الحساب، دون إدارة الأدوار.",
    };

    function showRoleNote(role) {
        const note = document.querySelector("[data-sui-role-note]");
        if (note) note.textContent = roleNotes[role] || "";
    }

    document.querySelector("[data-sui-role-select]")?.addEventListener("change", (event) => {
        showRoleNote(event.target.value);
    });

    document.querySelectorAll("[data-sui-staff-action]").forEach((button) => {
        button.addEventListener("click", () => {
            if (button.dataset.suiStaffAction === "role") {
                const form = document.querySelector("[data-sui-role-form]");
                if (!form) return;
                form.action = button.dataset.action || "";
                const select = form.querySelector("[data-sui-role-select]");
                if (select) select.value = button.dataset.role || "staff";
                const current = form.querySelector("[data-sui-role-current]");
                if (current) current.textContent = button.dataset.roleLabel || "";
                showRoleNote(select?.value || "");
            }

            if (button.dataset.suiStaffAction === "activation") {
                const form = document.querySelector("[data-sui-activation-form]");
                if (!form) return;
                const deactivate = button.dataset.activation === "deactivate";
                form.action = button.dataset.action || "";
                const action = form.querySelector("[name=action]");
                if (action) action.value = deactivate ? "deactivate" : "activate";
                const text = form.querySelector("[data-sui-activation-text]");
                const name = button.dataset.name || "";
                if (text) {
                    text.textContent = deactivate
                        ? "سيتم تسجيل خروج " + name + " فورًا، ولن يتمكن من الدخول حتى يُفعّل الحساب."
                        : "سيتمكن " + name + " من تسجيل الدخول من جديد.";
                }
                const title = document.getElementById("sui-modal-staff-activation");
                if (title) title.textContent = deactivate ? "تعطيل الحساب" : "تفعيل الحساب";
                const confirm = form.querySelector("[data-sui-activation-confirm]");
                if (confirm) {
                    confirm.textContent = deactivate ? "تعطيل الحساب" : "تفعيل الحساب";
                    confirm.classList.toggle("sui-btn--danger", deactivate);
                    confirm.classList.toggle("sui-btn--primary", !deactivate);
                }
            }
        });
    });

    const staffFilters = document.querySelector("[data-sui-staff-filters]");
    if (staffFilters) {
        let searchTimer = 0;
        const markLoading = () => {
            document.querySelector("[data-sui-staff-directory]")?.classList.add("is-loading");
        };
        staffFilters.querySelectorAll("select").forEach((select) => {
            select.addEventListener("change", () => {
                markLoading();
                staffFilters.requestSubmit();
            });
        });
        staffFilters.querySelector("[data-sui-staff-search]")?.addEventListener("input", () => {
            window.clearTimeout(searchTimer);
            searchTimer = window.setTimeout(() => {
                markLoading();
                staffFilters.requestSubmit();
            }, 300);
        });
        staffFilters.addEventListener("submit", markLoading);
    }

    document.querySelectorAll("[data-sui-row-href]").forEach((row) => {
        row.addEventListener("click", (event) => {
            if (event.target.closest("a, button, input, form, [data-sui-dropdown]")) return;
            const href = row.getAttribute("data-sui-row-href");
            if (href) window.location.assign(href);
        });
    });

    document.querySelectorAll("[data-sui-staff-form]").forEach((form) => {
        form.addEventListener("submit", () => {
            document.querySelector("[data-sui-staff-directory]")?.classList.add("is-loading");
        });
    });

    const programToggle = document.querySelector("[data-sui-program-toggle]");
    if (programToggle) {
        const stored = document.documentElement.getAttribute("data-sui-program-view") || "cards";
        const apply = (view) => {
            const next = view === "table" ? "table" : "cards";
            document.documentElement.setAttribute("data-sui-program-view", next);
            try {
                localStorage.setItem("staff-ui.programs.view", next);
            } catch (error) {}
            programToggle.querySelectorAll("[data-sui-program-view]").forEach((button) => {
                button.setAttribute("aria-pressed", button.getAttribute("data-sui-program-view") === next ? "true" : "false");
            });
        };
        apply(stored);
        programToggle.querySelectorAll("[data-sui-program-view]").forEach((button) => {
            button.addEventListener("click", () => apply(button.getAttribute("data-sui-program-view")));
        });
    }

    document.querySelectorAll("[data-sui-toast]").forEach((button) => {
        button.addEventListener("click", () => {
            toast(button.dataset.tone || "info", button.dataset.title || "تم", button.dataset.body || "");
        });
    });

    document.querySelectorAll("[data-sui-open-modal]").forEach((button) => {
        button.addEventListener("click", () => {
            const modal = document.querySelector('[data-sui-modal="' + button.dataset.suiOpenModal + '"]');
            modal?.removeAttribute("hidden");
            closeMenus(null);
        });
    });

    document.querySelectorAll("[data-sui-modal-close]").forEach((button) => {
        button.addEventListener("click", () => {
            button.closest("[data-sui-modal]")?.setAttribute("hidden", "");
        });
    });

    document.querySelectorAll("[data-sui-tabs]").forEach((group) => {
        const tabs = group.querySelectorAll("[data-sui-tab]");
        const table = document.querySelector('[data-sui-table][data-tab-group="' + group.dataset.suiTabs + '"]');
        tabs.forEach((tab) => {
            tab.addEventListener("click", () => {
                tabs.forEach((other) => {
                    other.classList.toggle("is-active", other === tab);
                    other.setAttribute("aria-selected", other === tab ? "true" : "false");
                });
                if (table) {
                    table.dataset.filter = tab.dataset.suiTab;
                    table.dispatchEvent(new Event("sui-filter"));
                }
            });
        });
    });

    document.querySelectorAll("[data-sui-table]").forEach((table) => {
        const rows = Array.from(table.querySelectorAll("[data-sui-row]"));
        const search = table.querySelector("[data-sui-table-search]");
        const empty = table.querySelector(".sui-table__empty");
        const perPage = Number(table.dataset.perPage || 6);
        let page = 1;
        let query = "";
        let sortKey = "";
        let sortDir = "asc";
        const filterOf = () => table.dataset.filter || "all";

        function matching() {
            const needle = query.trim();
            return rows.filter((row) => {
                const status = row.dataset.filter || "all";
                const text = row.dataset.search || "";
                const statusOk = filterOf() === "all" || status === filterOf();
                return statusOk && (needle === "" || text.includes(needle));
            });
        }

        function render() {
            const found = matching();
            if (sortKey) {
                found.sort((a, b) => {
                    const av = a.dataset["sort" + sortKey] || "";
                    const bv = b.dataset["sort" + sortKey] || "";
                    const an = Number(av);
                    const bn = Number(bv);
                    const cmp = Number.isFinite(an) && Number.isFinite(bn) && av !== "" && bv !== ""
                        ? an - bn
                        : av.localeCompare(bv, "ar");
                    return sortDir === "asc" ? cmp : -cmp;
                });
            }
            const pages = Math.max(1, Math.ceil(found.length / perPage));
            page = Math.min(page, pages);
            const start = (page - 1) * perPage;
            const visible = new Set(found.slice(start, start + perPage));
            rows.forEach((row) => row.classList.toggle("is-hidden", !visible.has(row)));
            const body = table.querySelector("tbody");
            found.forEach((row) => body.appendChild(row));
            table.classList.toggle("is-empty", found.length === 0);
            if (empty) empty.hidden = found.length !== 0;
            const from = found.length === 0 ? 0 : start + 1;
            const to = Math.min(start + perPage, found.length);
            const label = table.querySelector("[data-sui-page-label]");
            if (label) label.textContent = from + "–" + to + " من " + found.length;
            table.querySelectorAll("[data-sui-page]").forEach((button) => {
                const action = button.dataset.suiPage;
                button.disabled = (action === "first" || action === "prev") ? page <= 1 : page >= pages;
            });
        }

        search?.addEventListener("input", () => {
            query = search.value;
            page = 1;
            render();
        });

        table.addEventListener("sui-filter", () => {
            page = 1;
            render();
        });

        table.querySelectorAll("[data-sui-sort]").forEach((button) => {
            button.addEventListener("click", () => {
                const key = button.dataset.suiSort;
                if (sortKey === key) sortDir = sortDir === "asc" ? "desc" : "asc";
                else {
                    sortKey = key;
                    sortDir = "asc";
                }
                table.querySelectorAll("[data-sui-sort]").forEach((other) => {
                    other.classList.remove("is-asc", "is-desc");
                });
                button.classList.add(sortDir === "asc" ? "is-asc" : "is-desc");
                render();
            });
        });

        table.querySelectorAll("[data-sui-page]").forEach((button) => {
            button.addEventListener("click", () => {
                const found = matching();
                const pages = Math.max(1, Math.ceil(found.length / perPage));
                if (button.dataset.suiPage === "first") page = 1;
                if (button.dataset.suiPage === "prev") page = Math.max(1, page - 1);
                if (button.dataset.suiPage === "next") page = Math.min(pages, page + 1);
                if (button.dataset.suiPage === "last") page = pages;
                render();
            });
        });

        const checks = () => Array.from(table.querySelectorAll("[data-sui-row-check]"));
        const bulk = table.querySelector("[data-sui-bulk]");
        const bulkCount = table.querySelector("[data-sui-bulk-count]");
        const all = table.querySelector("[data-sui-check-all]");

        function syncBulk() {
            const selected = checks().filter((box) => box.checked && !box.closest("[data-sui-row]")?.classList.contains("is-hidden"));
            if (bulk) bulk.classList.toggle("is-on", selected.length > 0);
            if (bulkCount) bulkCount.textContent = "تم تحديد " + selected.length;
        }

        all?.addEventListener("change", () => {
            checks().forEach((box) => {
                const hidden = box.closest("[data-sui-row]")?.classList.contains("is-hidden");
                if (!hidden) box.checked = all.checked;
            });
            syncBulk();
        });

        checks().forEach((box) => box.addEventListener("change", syncBulk));
        table.querySelector("[data-sui-bulk-clear]")?.addEventListener("click", () => {
            checks().forEach((box) => { box.checked = false; });
            if (all) all.checked = false;
            syncBulk();
        });

        document.querySelector("[data-sui-loading]")?.addEventListener("click", () => {
            table.classList.add("is-loading");
            window.setTimeout(() => table.classList.remove("is-loading"), 900);
        });

        render();
    });

    const globalSearch = document.querySelector("[data-sui-global-search]");
    const tableSearch = document.querySelector("[data-sui-table-search]");
    globalSearch?.addEventListener("input", () => {
        if (!tableSearch) return;
        tableSearch.value = globalSearch.value;
        tableSearch.dispatchEvent(new Event("input"));
    });

    document.querySelectorAll("[data-sui-file]").forEach((input) => {
        input.addEventListener("change", () => {
            const name = input.parentElement?.querySelector("[data-sui-file-name]");
            if (name) name.textContent = input.files?.[0]?.name || "لم يُختر ملف";
        });
    });

    function chartFont() {
        return { family: getComputedStyle(document.body).fontFamily, size: 12 };
    }

    function gradient(chart, vertical) {
        const area = chart.chartArea;
        if (!area) return "#179298";
        const paint = chart.ctx.createLinearGradient(0, vertical ? area.bottom : area.left, 0, vertical ? area.top : area.bottom);
        paint.addColorStop(0, "#179298");
        paint.addColorStop(1, "#2BB3B9");
        return paint;
    }

    const trackPlugin = {
        id: "suiTracks",
        beforeDatasetsDraw(chart) {
            if (chart.config.type !== "bar") return;
            const { ctx, chartArea } = chart;
            const meta = chart.getDatasetMeta(0);
            ctx.save();
            meta.data.forEach((bar) => {
                const width = bar.width || 18;
                const x = bar.x - width / 2;
                const radius = 6;
                ctx.fillStyle = "#F3F4F6";
                ctx.beginPath();
                ctx.roundRect(x, chartArea.top, width, chartArea.bottom - chartArea.top, [radius, radius, 0, 0]);
                ctx.fill();
            });
            ctx.restore();
        },
    };

    document.querySelectorAll("[data-sui-chart]").forEach((canvas) => {
        if (!window.Chart) return;
        const labels = JSON.parse(canvas.dataset.labels || "[]");
        const values = JSON.parse(canvas.dataset.values || "[]");
        const kind = canvas.dataset.suiChart;
        const shared = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { rtl: true, textDirection: "rtl" },
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: "#6B7280", font: chartFont() },
                    border: { display: false },
                },
                y: {
                    position: "right",
                    grid: { color: "#ECECEF" },
                    ticks: { color: "#6B7280", font: chartFont() },
                    border: { display: false },
                    beginAtZero: true,
                },
            },
        };

        if (kind === "bar") {
            new window.Chart(canvas, {
                type: "bar",
                plugins: [trackPlugin],
                data: {
                    labels,
                    datasets: [{
                        data: values,
                        backgroundColor: (context) => gradient(context.chart, true),
                        borderRadius: { topLeft: 6, topRight: 6, bottomLeft: 0, bottomRight: 0 },
                        borderSkipped: false,
                        maxBarThickness: 22,
                    }],
                },
                options: shared,
            });
            return;
        }

        new window.Chart(canvas, {
            type: "line",
            data: {
                labels,
                datasets: [{
                    data: values,
                    borderColor: "#179298",
                    backgroundColor: (context) => {
                        const area = context.chart.chartArea;
                        if (!area) return "rgba(23,146,152,0.12)";
                        const paint = context.chart.ctx.createLinearGradient(0, area.top, 0, area.bottom);
                        paint.addColorStop(0, "rgba(43,179,185,0.35)");
                        paint.addColorStop(1, "rgba(23,146,152,0.02)");
                        return paint;
                    },
                    fill: true,
                    tension: 0.35,
                    pointRadius: 4,
                    pointBackgroundColor: "#ffffff",
                    pointBorderColor: "#179298",
                    pointBorderWidth: 2,
                    borderWidth: 2,
                }],
            },
            options: shared,
        });
    });

    refreshIcons();
})();
