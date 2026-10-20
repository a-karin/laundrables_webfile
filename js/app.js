/**
 * Laundrables Client-Side Application Logic (js/app.js)
 * Connects all pages (login, register, index, new-order, profile, service, admin)
 * to api.php for full database CRUD and real-time UI updates.
 */

document.addEventListener('DOMContentLoaded', () => {
    /**
     * Helper to call api.php via fetch
     */
    async function apiCall(action, payload = {}) {
        const res = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action, ...payload })
        });
        return await res.json();
    }

    /**
     * Format currency as ₱1,234.56
     */
    function formatPHP(val) {
        const num = Number(val) || 0;
        return '₱' + num.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    /**
     * Generic Modal Open / Close helpers
     */
    document.querySelectorAll('[data-close-modal]').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = btn.closest('.modal-backdrop');
            if (modal) modal.classList.remove('open');
        });
    });

    document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
        backdrop.addEventListener('click', (e) => {
            if (e.target === backdrop) backdrop.classList.remove('open');
        });
    });

    // ============================================================
    // 1. LOGIN PAGE
    // ============================================================
    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        const loginAlert = document.getElementById('login-alert');

        document.querySelectorAll('[data-fill-login]').forEach(btn => {
            btn.addEventListener('click', () => {
                document.getElementById('email').value = btn.dataset.email || '';
                document.getElementById('password').value = btn.dataset.pass || '';
            });
        });

        const forgotLink = document.getElementById('forgot-password-link');
        if (forgotLink) {
            forgotLink.addEventListener('click', (e) => {
                e.preventDefault();
                if (loginAlert) {
                    loginAlert.className = 'alert-banner show success';
                    loginAlert.textContent = 'Demo reset: Use password "password123" for customer accounts or "admin123" for admin@laundrables.com.';
                }
            });
        }

        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;

            const result = await apiCall('login', { email, password });
            if (loginAlert) {
                loginAlert.className = 'alert-banner show ' + (result.ok ? 'success' : 'error');
                loginAlert.textContent = result.message;
            }
            if (result.ok && result.redirect) {
                setTimeout(() => { window.location.href = result.redirect; }, 350);
            }
        });
    }

    // ============================================================
    // 2. REGISTER PAGE
    // ============================================================
    const registerForm = document.getElementById('register-form');
    if (registerForm) {
        const registerAlert = document.getElementById('register-alert');
        registerForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const payload = {
                first_name: document.getElementById('first-name').value.trim(),
                last_name: document.getElementById('last-name').value.trim(),
                email: document.getElementById('email').value.trim(),
                phone: document.getElementById('phone').value.trim(),
                password: document.getElementById('password').value,
                confirm_password: document.getElementById('confirm-password').value,
                terms: document.getElementById('terms').checked ? 1 : 0,
                offers: document.getElementById('offers').checked ? 1 : 0
            };

            const result = await apiCall('register', payload);
            if (registerAlert) {
                registerAlert.className = 'alert-banner show ' + (result.ok ? 'success' : 'error');
                registerAlert.textContent = result.message;
            }
            if (result.ok && result.redirect) {
                setTimeout(() => { window.location.href = result.redirect; }, 450);
            }
        });
    }

    // ============================================================
    // 3. CUSTOMER DASHBOARD (index.php) - Track & Edit Active Order
    // ============================================================
    const trackOrderBtn = document.getElementById('track-order-btn');
    const trackModal = document.getElementById('track-order-modal');
    if (trackOrderBtn && trackModal) {
        trackOrderBtn.addEventListener('click', () => {
            trackModal.classList.add('open');
        });
    }

    const customerUpdateOrderForm = document.getElementById('customer-update-order-form');
    if (customerUpdateOrderForm) {
        customerUpdateOrderForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const orderId = document.getElementById('track-order-id').value;
            const careInstructions = document.getElementById('track-care-instructions').value.trim();
            const res = await apiCall('update_order', {
                id: orderId,
                care_instructions: careInstructions
            });
            const msg = document.getElementById('track-order-message');
            if (msg) {
                msg.style.display = 'block';
                msg.textContent = res.message;
            }
            if (res.ok) {
                setTimeout(() => window.location.reload(), 600);
            }
        });
    }

    // ============================================================
    // 4. NEW ORDER PAGE (new-order.php) - Live Price Calculator & Submit
    // ============================================================
    const serviceButtons = document.querySelectorAll('.service-option');
    const quantityInput = document.getElementById('quantity');
    const continueButton = document.getElementById('continue-button');

    if (serviceButtons.length > 0 && quantityInput) {
        let selectedService = {
            name: 'Wash & Fold',
            price: 60,
            unit: 'kg',
            turnaround: '24 hours',
            min: 3,
            step: 0.5
        };

        const quantityLabel = document.getElementById('quantity-label');
        const quantityHint = document.getElementById('quantity-hint');
        const summaryService = document.getElementById('summary-service');
        const summaryTurnaround = document.getElementById('turnaround');
        const priceDetail = document.getElementById('price-detail');
        const subtotalEl = document.getElementById('subtotal');
        const totalEl = document.getElementById('total');

        function recalculateOrderSummary() {
            let qty = parseFloat(quantityInput.value);
            if (isNaN(qty) || qty < selectedService.min) {
                qty = selectedService.min;
            }
            const total = qty * selectedService.price;
            const unitLabel = selectedService.unit === 'item'
                ? (qty === 1 ? 'piece' : 'pieces')
                : 'kg';
            const formattedQty = selectedService.unit === 'item'
                ? Math.round(qty)
                : qty.toFixed(1);

            if (summaryService) summaryService.textContent = selectedService.name;
            if (summaryTurnaround) summaryTurnaround.textContent = selectedService.turnaround;
            if (priceDetail) priceDetail.textContent = `${formattedQty} ${unitLabel} × ${formatPHP(selectedService.price)}`;
            if (subtotalEl) subtotalEl.textContent = formatPHP(total);
            if (totalEl) totalEl.textContent = formatPHP(total);
        }

        serviceButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                serviceButtons.forEach(b => b.classList.remove('selected'));
                btn.classList.add('selected');

                const name = btn.dataset.name || 'Wash & Fold';
                const price = parseFloat(btn.dataset.price || '60');
                const unit = btn.dataset.unit || 'kg';

                if (unit === 'item') {
                    selectedService = { name, price, unit: 'item', turnaround: '48 hours', min: 1, step: 1 };
                    if (quantityLabel) quantityLabel.textContent = 'Number of pieces *';
                    if (quantityHint) quantityHint.textContent = '1 piece minimum. Final count is confirmed at drop-off.';
                    quantityInput.min = '1';
                    quantityInput.step = '1';
                    // Start at 1 piece so a kg amount (like 8.5) doesn't turn into 9 pieces
                    quantityInput.value = 1;
                } else {
                    const isSpecial = name === 'Special Care';
                    selectedService = {
                        name,
                        price,
                        unit: 'kg',
                        turnaround: isSpecial ? '36 hours' : '24 hours',
                        min: isSpecial ? 2 : 3,
                        step: 0.5
                    };
                    if (quantityLabel) quantityLabel.textContent = 'Estimated weight (kg) *';
                    if (quantityHint) quantityHint.textContent = `${selectedService.min} kg minimum. Final weight is confirmed at drop-off.`;
                    quantityInput.min = String(selectedService.min);
                    quantityInput.step = '0.5';
                    if (parseFloat(quantityInput.value) < selectedService.min) {
                        quantityInput.value = String(selectedService.min);
                    }
                }
                recalculateOrderSummary();
            });
        });

        quantityInput.addEventListener('input', recalculateOrderSummary);
        quantityInput.addEventListener('change', () => {
            let qty = parseFloat(quantityInput.value);
            if (isNaN(qty) || qty < selectedService.min) {
                quantityInput.value = selectedService.min;
            }
            recalculateOrderSummary();
        });

        // ---- Drop-off date and time slots (Manila time from the server) ----
        const dropDate = document.getElementById('drop-date');
        const dropTime = document.getElementById('drop-time');
        const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July',
            'August', 'September', 'October', 'November', 'December'];

        // "2026-10-20" -> "October 20, 2026"
        function longDate(iso) {
            const [y, m, d] = iso.split('-').map(Number);
            return `${MONTHS[m - 1]} ${d}, ${y}`;
        }

        function addDays(iso, n) {
            const [y, m, d] = iso.split('-').map(Number);
            const dt = new Date(Date.UTC(y, m - 1, d + n));
            return dt.toISOString().slice(0, 10);
        }

        let refreshSlots = () => {};

        if (dropDate && dropTime) {
            const todayIso = dropDate.dataset.today;
            const loadedAt = Date.now();
            const startMinutes = parseInt(dropDate.dataset.nowMinutes, 10) || 0;

            // Current minutes since midnight in Manila (server time plus time on page)
            function nowMinutes() {
                return startMinutes + Math.floor((Date.now() - loadedAt) / 60000);
            }

            refreshSlots = function () {
                // Never allow a date before today, even if it is typed in by hand
                if (!dropDate.value || dropDate.value < todayIso) {
                    dropDate.value = todayIso;
                }
                if (dropDate.value > dropDate.max) {
                    dropDate.value = dropDate.max;
                }

                const isToday = dropDate.value === todayIso;
                const options = Array.from(dropTime.options);
                options.forEach(opt => {
                    const started = isToday && nowMinutes() >= parseInt(opt.dataset.hour, 10) * 60;
                    opt.disabled = started;
                });

                const open = options.filter(o => !o.disabled);
                if (open.length === 0) {
                    // Every slot today has started, so move to tomorrow
                    dropDate.value = addDays(todayIso, 1);
                    options.forEach(o => { o.disabled = false; });
                } else if (dropTime.selectedOptions[0]?.disabled) {
                    dropTime.value = open[0].value;
                }
            };

            dropDate.addEventListener('change', refreshSlots);
            dropDate.addEventListener('input', refreshSlots);
            refreshSlots();
        }

        if (continueButton) {
            continueButton.addEventListener('click', async () => {
                refreshSlots();
                continueButton.disabled = true;
                continueButton.textContent = 'Saving your order...';

                const payload = {
                    service_name: selectedService.name,
                    unit_price: selectedService.price,
                    quantity: parseFloat(quantityInput.value) || selectedService.min,
                    care_instructions: document.getElementById('instructions')?.value.trim() || '',
                    fragrance_free: document.getElementById('pref-fragrance-free')?.checked ? 1 : 0,
                    low_heat: document.getElementById('pref-low-heat')?.checked ? 1 : 0,
                    dropoff_date: dropDate && dropDate.value ? longDate(dropDate.value) : '',
                    dropoff_time: dropTime ? dropTime.value : ''
                };

                const result = await apiCall('create_order', payload);
                const feedback = document.getElementById('order-feedback');
                if (feedback) {
                    feedback.className = 'alert-banner show ' + (result.ok ? 'success' : 'error');
                    feedback.textContent = result.message;
                }

                if (result.ok) {
                    setTimeout(() => {
                        window.location.href = result.redirect || 'index.php';
                    }, 600);
                } else {
                    continueButton.disabled = false;
                    continueButton.textContent = 'Continue to confirmation →';
                }
            });
        }
    }

    // ============================================================
    // 5. PROFILE PAGE (profile.php) - Info, Password, Automatic Photo, Preferences
    // ============================================================
    const profileForm = document.getElementById('profile-form');
    if (profileForm) {
        profileForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const payload = {
                first_name: document.getElementById('first-name').value.trim(),
                last_name: document.getElementById('last-name').value.trim(),
                email: document.getElementById('email').value.trim(),
                phone: document.getElementById('phone').value.trim(),
                address: document.getElementById('address').value.trim(),
                city: document.getElementById('city').value.trim(),
                zip_code: document.getElementById('zip').value.trim()
            };
            const res = await apiCall('update_profile', payload);
            const msg = document.getElementById('profile-message');
            if (msg) {
                msg.style.display = 'block';
                msg.textContent = res.message;
            }
            if (res.ok) {
                const nameEl = document.getElementById('customer-name');
                if (nameEl) nameEl.textContent = res.full_name;
                const topProfile = document.getElementById('profile');
                if (topProfile) {
                    topProfile.innerHTML = `<strong>${res.full_name}</strong><small>Customer</small>`;
                }
                const avatarEl = document.getElementById('avatar');
                if (avatarEl && !avatarEl.querySelector('img')) {
                    avatarEl.textContent = res.initials;
                }
            }
        });
    }

    const passwordForm = document.getElementById('password-form');
    if (passwordForm) {
        passwordForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const payload = {
                current_password: document.getElementById('current-password').value,
                new_password: document.getElementById('new-password').value,
                confirm_password: document.getElementById('confirm-password').value
            };
            const res = await apiCall('update_password', payload);
            const msg = document.getElementById('password-message');
            if (msg) {
                msg.style.display = 'block';
                msg.textContent = res.message;
            }
            if (res.ok) {
                passwordForm.reset();
            }
        });
    }

    // Automatic picture upload & preview when file input changes
    const photoUpload = document.getElementById('photo-upload');
    if (photoUpload) {
        photoUpload.addEventListener('change', () => {
            const file = photoUpload.files && photoUpload.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = async (ev) => {
                const dataUrl = ev.target.result;
                const avatarEl = document.getElementById('avatar');
                if (avatarEl) {
                    avatarEl.innerHTML = `<img src="${dataUrl}" alt="Customer photo">`;
                }
                const res = await apiCall('upload_avatar', { avatar_data: dataUrl });
                const msg = document.getElementById('profile-message');
                if (msg) {
                    msg.style.display = 'block';
                    msg.textContent = res.message;
                }
            };
            reader.readAsDataURL(file);
        });
    }

    const preferencesForm = document.getElementById('preferences-form');
    if (preferencesForm) {
        preferencesForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const payload = {
                fragrance_free: preferencesForm.querySelector('[name="fragrance_free"]')?.checked ? 1 : 0,
                low_heat: preferencesForm.querySelector('[name="low_heat"]')?.checked ? 1 : 0,
                sms_alerts: preferencesForm.querySelector('[name="sms_alerts"]')?.checked ? 1 : 0,
                email_updates: preferencesForm.querySelector('[name="email_updates"]')?.checked ? 1 : 0,
                offers: preferencesForm.querySelector('[name="offers"]')?.checked ? 1 : 0
            };
            const res = await apiCall('update_preferences', payload);
            const msg = document.getElementById('preferences-message');
            if (msg) {
                msg.style.display = 'block';
                msg.textContent = res.message;
            }
        });
    }

    // ============================================================
    // 6. HELP & SUPPORT PAGE (service.php) - Ticket Creation & Replies
    // ============================================================
    const supportForm = document.getElementById('support-form');
    if (supportForm) {
        supportForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const requestType = supportForm.querySelector('input[name="request_type"]:checked')?.value || 'help';
            const subject = document.getElementById('subject').value.trim();
            const orderNumber = document.getElementById('order-number').value.trim();
            const message = document.getElementById('message').value.trim();

            if (!subject || !message) return;

            const res = await apiCall('create_support', {
                request_type: requestType,
                subject,
                order_number: orderNumber,
                message
            });

            const msgEl = document.getElementById('support-message');
            if (msgEl) {
                msgEl.style.display = 'block';
                msgEl.textContent = res.message;
            }

            if (res.ok) {
                supportForm.reset();
                setTimeout(() => window.location.reload(), 650);
            }
        });
    }

    // View / Reply to Support Ticket Modal
    const supportModal = document.getElementById('support-ticket-modal');
    function bindSupportTicketLinks() {
        document.querySelectorAll('[data-request]').forEach(link => {
            link.addEventListener('click', async (e) => {
                e.preventDefault();
                const ticketNo = link.dataset.request;
                const res = await apiCall('get_support', { ticket_number: ticketNo });
                if (!res.ok) return;

                if (supportModal) {
                    document.getElementById('modal-ticket-title').textContent = `${res.ticket.subject} (#${res.ticket.ticket_number})`;
                    document.getElementById('modal-ticket-meta').textContent = `Created ${res.ticket.created_date} · Status: ${res.ticket.status}` + (res.ticket.order_number ? ` · Order ${res.ticket.order_number}` : '');
                    document.getElementById('reply-ticket-number').value = res.ticket.ticket_number;
                    const statusSelect = document.getElementById('reply-ticket-status');
                    if (statusSelect) statusSelect.value = res.ticket.status;

                    const threadEl = document.getElementById('modal-ticket-thread');
                    threadEl.innerHTML = (res.replies || []).map(r => `
                        <div class="reply-bubble ${r.sender_role === 'admin' ? 'admin' : ''}">
                            <strong>${r.sender_name}</strong>
                            <div>${r.reply_text}</div>
                        </div>
                    `).join('');
                    supportModal.classList.add('open');
                }
            });
        });
    }
    bindSupportTicketLinks();

    const supportReplyForm = document.getElementById('support-reply-form');
    if (supportReplyForm) {
        supportReplyForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const ticketNumber = document.getElementById('reply-ticket-number').value;
            const replyText = document.getElementById('reply-ticket-text').value.trim();
            const statusSelect = document.getElementById('reply-ticket-status');
            const status = statusSelect ? statusSelect.value : '';

            if (!replyText) return;
            const res = await apiCall('reply_support', {
                ticket_number: ticketNumber,
                reply_text: replyText,
                status
            });
            if (res.ok) {
                document.getElementById('reply-ticket-text').value = '';
                setTimeout(() => window.location.reload(), 400);
            }
        });
    }

    // ============================================================
    // 7. ADMIN DASHBOARD (admin.php) - Search, Filter, Pagination & Full Order CRUD
    // ============================================================
    const adminOrdersTable = document.getElementById('admin-orders-tbody');
    if (adminOrdersTable) {
        const searchInput = document.getElementById('admin-search-input');
        const serviceFilter = document.getElementById('admin-service-filter');
        const queueFilter = document.getElementById('admin-queue-filter');
        const countLabel = document.getElementById('admin-table-count');
        const paginationNav = document.getElementById('admin-pagination');

        let currentPage = 1;
        const rowsPerPage = 8;

        function applyAdminFilters() {
            const q = (searchInput?.value || '').toLowerCase().trim();
            const svc = serviceFilter?.value || 'All services';
            const queue = queueFilter?.value || 'All orders';

            const allRows = Array.from(adminOrdersTable.querySelectorAll('tr[data-order-row]'));
            const matchedRows = allRows.filter(row => {
                const orderNo = (row.dataset.orderNumber || '').toLowerCase();
                const cust = (row.dataset.customerName || '').toLowerCase();
                const rowSvc = row.dataset.serviceName || '';
                const rowStatus = row.dataset.laundryStatus || '';
                const isToday = row.dataset.todayQueue === '1';

                const matchesSearch = !q || orderNo.includes(q) || cust.includes(q);
                const matchesService = (svc === 'All services') || (rowSvc === svc);
                let matchesQueue = true;
                if (queue === "Today's queue") {
                    matchesQueue = isToday;
                } else if (['Waiting', 'In progress', 'Ready for pickup', 'Completed'].includes(queue)) {
                    matchesQueue = (rowStatus === queue);
                }

                return matchesSearch && matchesService && matchesQueue;
            });

            const totalMatched = matchedRows.length;
            const totalPages = Math.max(1, Math.ceil(totalMatched / rowsPerPage));
            if (currentPage > totalPages) currentPage = totalPages;

            const startIdx = (currentPage - 1) * rowsPerPage;
            const endIdx = Math.min(startIdx + rowsPerPage, totalMatched);

            allRows.forEach(r => { r.style.display = 'none'; });
            matchedRows.slice(startIdx, endIdx).forEach(r => { r.style.display = ''; });

            if (countLabel) {
                const shownStart = totalMatched === 0 ? 0 : startIdx + 1;
                countLabel.innerHTML = `Showing <b>${shownStart}–${endIdx}</b> of <b>${totalMatched}</b> orders`;
            }

            if (paginationNav) {
                let html = `<a href="#prev" data-page="${Math.max(1, currentPage - 1)}">‹</a>`;
                for (let p = 1; p <= totalPages; p++) {
                    html += `<a href="#page-${p}" data-page="${p}" class="${p === currentPage ? 'active' : ''}">${p}</a>`;
                }
                html += `<a href="#next" data-page="${Math.min(totalPages, currentPage + 1)}">›</a>`;
                paginationNav.innerHTML = html;

                paginationNav.querySelectorAll('[data-page]').forEach(a => {
                    a.addEventListener('click', (e) => {
                        e.preventDefault();
                        currentPage = parseInt(a.dataset.page, 10) || 1;
                        applyAdminFilters();
                    });
                });
            }
        }

        if (searchInput) searchInput.addEventListener('input', () => { currentPage = 1; applyAdminFilters(); });
        if (serviceFilter) serviceFilter.addEventListener('change', () => { currentPage = 1; applyAdminFilters(); });
        if (queueFilter) queueFilter.addEventListener('change', () => { currentPage = 1; applyAdminFilters(); });
        applyAdminFilters();

        // Admin Create Order Modal
        const openCreateModalBtn = document.getElementById('open-create-order-modal');
        const createOrderModal = document.getElementById('admin-create-order-modal');
        if (openCreateModalBtn && createOrderModal) {
            openCreateModalBtn.addEventListener('click', (e) => {
                e.preventDefault();
                createOrderModal.classList.add('open');
            });
        }

        const adminCreateForm = document.getElementById('admin-create-order-form');
        if (adminCreateForm) {
            adminCreateForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const payload = {
                    customer_name: document.getElementById('create-customer-name').value.trim(),
                    service_name: document.getElementById('create-service-name').value,
                    quantity: parseFloat(document.getElementById('create-quantity').value) || 3,
                    total_amount: parseFloat(document.getElementById('create-total-amount').value) || 300,
                    laundry_status: document.getElementById('create-laundry-status').value,
                    payment_status: document.getElementById('create-payment-status').value,
                    care_instructions: document.getElementById('create-care-instructions').value.trim(),
                    // Date is left out so the server uses today's date (Manila time)
                    dropoff_time: 'Walk-in'
                };
                const res = await apiCall('create_order', payload);
                if (res.ok) {
                    window.location.reload();
                }
            });
        }

        // Admin Edit / Update / Delete Order Modal
        const editOrderModal = document.getElementById('admin-edit-order-modal');
        document.querySelectorAll('[data-edit-order]').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.preventDefault();
                const orderId = btn.dataset.editOrder;
                const res = await apiCall('get_order', { id: orderId });
                if (!res.ok || !editOrderModal) return;

                const o = res.order;
                document.getElementById('edit-order-id').value = o.id;
                document.getElementById('edit-order-title').textContent = `Manage Order ${o.order_number}`;
                document.getElementById('edit-customer-name').value = o.customer_name;
                document.getElementById('edit-service-name').value = o.service_name;
                document.getElementById('edit-quantity').value = o.quantity;
                document.getElementById('edit-total-amount').value = o.total_amount;
                document.getElementById('edit-laundry-status').value = o.laundry_status;
                document.getElementById('edit-payment-status').value = o.payment_status;
                document.getElementById('edit-pickup-estimate').value = o.pickup_estimate || '';
                document.getElementById('edit-care-instructions').value = o.care_instructions || '';

                editOrderModal.classList.add('open');
            });
        });

        const adminEditForm = document.getElementById('admin-edit-order-form');
        if (adminEditForm) {
            adminEditForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const payload = {
                    id: document.getElementById('edit-order-id').value,
                    customer_name: document.getElementById('edit-customer-name').value.trim(),
                    service_name: document.getElementById('edit-service-name').value,
                    quantity: parseFloat(document.getElementById('edit-quantity').value) || 1,
                    total_amount: parseFloat(document.getElementById('edit-total-amount').value) || 0,
                    laundry_status: document.getElementById('edit-laundry-status').value,
                    payment_status: document.getElementById('edit-payment-status').value,
                    pickup_estimate: document.getElementById('edit-pickup-estimate').value.trim(),
                    care_instructions: document.getElementById('edit-care-instructions').value.trim()
                };
                const res = await apiCall('update_order', payload);
                if (res.ok) {
                    window.location.reload();
                }
            });
        }

        const deleteOrderBtn = document.getElementById('admin-delete-order-btn');
        if (deleteOrderBtn) {
            deleteOrderBtn.addEventListener('click', async () => {
                const orderId = document.getElementById('edit-order-id').value;
                if (!orderId) return;
                const res = await apiCall('delete_order', { id: orderId });
                if (res.ok) {
                    window.location.reload();
                }
            });
        }
    }
});
