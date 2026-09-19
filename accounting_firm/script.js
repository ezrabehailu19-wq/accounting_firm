/* =========================================================
   Selamawit H/Mariam — front-end behaviour
   ---------------------------------------------------------
   One file for the public site and the portal. Every block
   checks for the elements it needs and does nothing when
   they are absent, so the same file is safe on every page.

   Nothing here is load-bearing: forms post, links navigate
   and tables read correctly with JavaScript switched off.
   This layer only adds convenience on top.
   ========================================================= */
(function () {
    'use strict';

    var $  = function (sel, root) { return (root || document).querySelector(sel); };
    var $$ = function (sel, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(sel));
    };

    var prefersReducedMotion = window.matchMedia
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ---------- 1. Sticky header shadow ---------- */
    var header = $('#header');
    if (header) {
        var onScroll = function () {
            header.classList.toggle('scrolled', window.scrollY > 24);
        };
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }

    /* ---------- 2. Back to top ---------- */
    var scrollTopBtn = $('#scrollTop');
    if (scrollTopBtn) {
        window.addEventListener('scroll', function () {
            scrollTopBtn.classList.toggle('visible', window.scrollY > 400);
        }, { passive: true });

        scrollTopBtn.addEventListener('click', function () {
            window.scrollTo({
                top: 0,
                behavior: prefersReducedMotion ? 'auto' : 'smooth'
            });
        });
    }

    /* ---------- 3. Mobile navigation ---------- */
    var menuToggle = $('#menuToggle');
    var navMenu    = $('#navMenu');

    if (menuToggle && navMenu) {
        menuToggle.addEventListener('click', function () {
            var open = navMenu.classList.toggle('active');
            menuToggle.classList.toggle('active', open);
            // Screen readers need to know the state, not just see the icon change.
            menuToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        $$('.nav-link').forEach(function (link) {
            link.addEventListener('click', function () {
                navMenu.classList.remove('active');
                menuToggle.classList.remove('active');
                menuToggle.setAttribute('aria-expanded', 'false');
            });
        });
    }

    /* ---------- 4. Pointer tilt ---------- */
    /* The hero image rotates towards the cursor. Driven by CSS custom
       properties so the transform itself stays in the stylesheet, and
       skipped entirely on touch devices and for anyone who has asked
       for reduced motion. */
    var tiltHosts = $$('[data-tilt]');

    if (tiltHosts.length && !prefersReducedMotion && window.matchMedia('(hover: hover)').matches) {
        tiltHosts.forEach(function (host) {
            var target = host.querySelector('.hero-img') || host;
            var frame  = null;

            host.addEventListener('mousemove', function (event) {
                if (frame) { return; }

                frame = requestAnimationFrame(function () {
                    frame = null;

                    var box = host.getBoundingClientRect();
                    // -0.5 … 0.5 from the centre of the element
                    var px = (event.clientX - box.left) / box.width - 0.5;
                    var py = (event.clientY - box.top) / box.height - 0.5;

                    target.style.setProperty('--ry', (px * 14).toFixed(2) + 'deg');
                    target.style.setProperty('--rx', (-py * 10).toFixed(2) + 'deg');
                });
            });

            host.addEventListener('mouseleave', function () {
                target.style.setProperty('--ry', '-7deg');
                target.style.setProperty('--rx', '3deg');
            });
        });
    }

    /* ---------- 5. Counting statistics ---------- */
    var statNumbers = $$('.stat-number');

    if (statNumbers.length) {
        var runCount = function (el) {
            var target = parseInt(el.getAttribute('data-target'), 10) || 0;

            if (prefersReducedMotion) {
                el.textContent = target.toLocaleString();
                return;
            }

            var started = null;
            var duration = 1400;

            var step = function (timestamp) {
                if (started === null) { started = timestamp; }

                var progress = Math.min((timestamp - started) / duration, 1);
                // Ease-out, so the number decelerates into place rather
                // than stopping dead.
                var eased = 1 - Math.pow(1 - progress, 3);

                el.textContent = Math.round(target * eased).toLocaleString();

                if (progress < 1) { requestAnimationFrame(step); }
            };

            requestAnimationFrame(step);
        };

        // Only count once the figures are actually on screen.
        if ('IntersectionObserver' in window) {
            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        runCount(entry.target);
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.4 });

            statNumbers.forEach(function (el) { observer.observe(el); });
        } else {
            statNumbers.forEach(runCount);
        }
    }

    /* ---------- 6. Service search and filtering ---------- */
    var serviceSearch = $('#serviceSearch');
    var servicesGrid  = $('#servicesGrid');
    var filterBtns    = $$('.filter-btn');

    if (servicesGrid) {
        var activeFilter = 'all';

        var applyFilters = function () {
            var term  = serviceSearch ? serviceSearch.value.trim().toLowerCase() : '';
            var cards = $$('.service-card', servicesGrid);
            var shown = 0;

            cards.forEach(function (card) {
                var category = (card.getAttribute('data-category') || '').toLowerCase();
                var text     = card.textContent.toLowerCase();

                var matchesCategory = activeFilter === 'all' || category.indexOf(activeFilter) !== -1;
                var matchesTerm     = term === '' || text.indexOf(term) !== -1;
                var visible         = matchesCategory && matchesTerm;

                card.style.display = visible ? '' : 'none';
                if (visible) { shown++; }
            });

            var message = $('#noResults');

            if (shown === 0) {
                if (!message) {
                    message = document.createElement('div');
                    message.id = 'noResults';
                    message.className = 'empty';
                    message.innerHTML = '<h3>Nothing matches that</h3>'
                        + '<p>Try a shorter search term, or clear the filters to see everything.</p>';
                    servicesGrid.appendChild(message);
                }
                message.style.display = '';
            } else if (message) {
                message.style.display = 'none';
            }
        };

        if (serviceSearch) {
            serviceSearch.addEventListener('input', applyFilters);
        }

        filterBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                filterBtns.forEach(function (other) {
                    other.classList.remove('active');
                    other.setAttribute('aria-pressed', 'false');
                });

                btn.classList.add('active');
                btn.setAttribute('aria-pressed', 'true');
                activeFilter = (btn.getAttribute('data-filter') || 'all').toLowerCase();

                applyFilters();
            });
        });
    }

    /* ---------- 7. FAQ accordion ---------- */
    $$('.faq-item').forEach(function (item) {
        var question = item.querySelector('.faq-question');
        if (!question) { return; }

        question.setAttribute('aria-expanded', 'false');

        question.addEventListener('click', function () {
            var isOpen = item.classList.contains('active');

            // One open at a time keeps the page from growing under the
            // reader's cursor as they work down the list.
            $$('.faq-item.active').forEach(function (other) {
                other.classList.remove('active');
                var otherQuestion = other.querySelector('.faq-question');
                if (otherQuestion) { otherQuestion.setAttribute('aria-expanded', 'false'); }
            });

            if (!isOpen) {
                item.classList.add('active');
                question.setAttribute('aria-expanded', 'true');
            }
        });
    });

    /* ---------- 8. Contact form ---------- */
    var contactForm  = $('#contactForm');
    var successModal = $('#successModal');
    var closeModal   = $('#closeModal');

    if (contactForm) {
        var csrfToken = '';

        // Fetched rather than printed into the page, because this is a
        // static .html file — there is no PHP here to render one.
        fetch('csrf_token.php', { credentials: 'same-origin' })
            .then(function (response) { return response.json(); })
            .then(function (data) { csrfToken = data.token || ''; })
            .catch(function () { /* left empty; the server will reject the post */ });

        var setError = function (id, text) {
            var el = document.getElementById(id);
            if (el) { el.textContent = text || ''; }
        };

        var clearErrors = function () {
            $$('.error-message', contactForm).forEach(function (el) { el.textContent = ''; });
            $$('input, textarea, select', contactForm).forEach(function (el) {
                el.removeAttribute('aria-invalid');
            });
        };

        contactForm.addEventListener('submit', function (event) {
            event.preventDefault();
            clearErrors();

            var fullname = $('#fullname');
            var email    = $('#email');
            var message  = $('#message');
            var formMsg  = $('#formMessage');
            var submit   = contactForm.querySelector('button[type="submit"]');
            var valid    = true;

            // Checked here for a fast answer, and again on the server,
            // which is the check that actually counts.
            if (!fullname.value.trim() || fullname.value.trim().length < 2) {
                setError('nameError', 'Enter your name.');
                fullname.setAttribute('aria-invalid', 'true');
                valid = false;
            }

            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
                setError('emailError', 'Enter a valid email address.');
                email.setAttribute('aria-invalid', 'true');
                valid = false;
            }

            if (message.value.trim().length < 10) {
                setError('messageError', 'Tell us a bit more — at least 10 characters.');
                message.setAttribute('aria-invalid', 'true');
                valid = false;
            }

            if (!valid) { return; }

            var payload = new FormData(contactForm);
            payload.append('csrf_token', csrfToken);

            if (submit) {
                submit.disabled = true;
                submit.textContent = 'Sending…';
            }

            fetch('contact_submit.php', {
                method: 'POST',
                body: payload,
                credentials: 'same-origin'
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (data.success) {
                        contactForm.reset();

                        if (successModal) {
                            var reference = successModal.querySelector('[data-reference]');
                            if (reference && data.reference) {
                                reference.textContent = data.reference;
                            }
                            successModal.classList.add('active');
                        } else if (formMsg) {
                            formMsg.innerHTML = '<div class="notice notice--success">'
                                + (data.message || 'Thank you — we will be in touch.') + '</div>';
                        }

                        return;
                    }

                    // Field-level errors from the server land on the
                    // matching inputs rather than in one lump at the top.
                    var map = {
                        fullname: 'nameError',
                        email: 'emailError',
                        message: 'messageError'
                    };

                    Object.keys(data.errors || {}).forEach(function (field) {
                        if (map[field]) { setError(map[field], data.errors[field]); }
                    });

                    if (formMsg) {
                        formMsg.innerHTML = '<div class="notice notice--error">'
                            + (data.error || 'That did not go through. Please try again.') + '</div>';
                    }
                })
                .catch(function () {
                    if (formMsg) {
                        formMsg.innerHTML = '<div class="notice notice--error">'
                            + 'We could not reach the server. Check your connection and try again.</div>';
                    }
                })
                .then(function () {
                    if (submit) {
                        submit.disabled = false;
                        submit.textContent = 'Send message';
                    }
                });
        });
    }

    if (successModal) {
        var dismiss = function () { successModal.classList.remove('active'); };

        if (closeModal) { closeModal.addEventListener('click', dismiss); }

        successModal.addEventListener('click', function (event) {
            if (event.target === successModal) { dismiss(); }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') { dismiss(); }
        });
    }

    /* ---------- 9. Cost estimator ---------- */
    var serviceType     = $('#serviceType');
    var serviceDuration = $('#serviceDuration');
    var calculateBtn    = $('#calculateBtn');
    var totalAmount     = $('#totalAmount');
    var estimateResult  = $('#estimateResult');

    if (calculateBtn) {
        calculateBtn.addEventListener('click', function () {
            var basePrice = 0;
            var months    = parseInt(serviceDuration ? serviceDuration.value : '1', 10) || 1;
            var extras    = 0;

            if (serviceType && serviceType.value) {
                var chosen = serviceType.options[serviceType.selectedIndex];
                basePrice  = parseInt(chosen.getAttribute('data-price'), 10) || 0;
            }

            if (!basePrice) {
                if (totalAmount) { totalAmount.textContent = 'Choose a service first'; }
                if (estimateResult) { estimateResult.style.display = 'block'; }
                return;
            }

            // Extras are one-off fees, so they are added after the
            // monthly rate has been multiplied out.
            $$('.checkbox-group input[type="checkbox"]').forEach(function (box) {
                if (box.checked) {
                    extras += parseInt(box.getAttribute('data-price'), 10) || 0;
                }
            });

            var total = (basePrice * months) + extras;

            if (totalAmount) {
                totalAmount.textContent = total.toLocaleString() + ' ETB';
            }
            if (estimateResult) {
                estimateResult.style.display = 'block';
            }
        });
    }

    /* ---------- 10. Document checklist download ---------- */
    var checklistService = $('#checklistService');
    var checklistBtn     = $('#downloadChecklistBtn');
    var checklistInfo    = $('#checklistInfo');

    var serviceDocuments = {
        tax: [
            'Valid ID (passport or national ID)',
            'Tax Identification Number (TIN)',
            'Business licence, for businesses',
            'Previous tax returns — last three years',
            'Income statements and payslips',
            'Bank statements — last twelve months',
            'Investment income statements',
            'Property ownership documents',
            'Business expense records',
            'VAT registration certificate, if registered'
        ],
        bookkeeping: [
            'Valid ID (passport or national ID)',
            'Business licence',
            'Bank statements — last six months',
            'Sales invoices and receipts',
            'Purchase invoices and bills',
            'Expense reports',
            'Previous accounting records',
            'Loan agreements and statements',
            'Asset purchase documents',
            'Inventory records, if you hold stock'
        ],
        payroll: [
            'Valid ID (passport or national ID)',
            'Business licence',
            'Employee records and contracts',
            'Previous payroll records',
            'Tax registration certificates',
            'Pension registration documents',
            'Employee bank account details',
            'Leave and absence records',
            'Overtime calculation records',
            'Benefits and deduction documents'
        ],
        advisory: [
            'Valid ID (passport or national ID)',
            'Business licence',
            'Current financial statements',
            'Business plan documents',
            'Loan agreements and statements',
            'Cash flow statements',
            'Budget versus actual reports',
            'Market research data',
            'Competitor analysis',
            'Growth projections and forecasts'
        ],
        audit: [
            'Valid ID (passport or national ID)',
            'Business licence',
            'Previous audit reports',
            'Current financial statements',
            'General ledger detail',
            'Bank reconciliation statements',
            'Inventory valuation reports',
            'Fixed asset register',
            'Tax clearance certificates',
            'Board meeting minutes',
            'Legal agreements and contracts'
        ]
    };

    if (checklistService) {
        checklistService.addEventListener('change', function () {
            var known = Object.prototype.hasOwnProperty.call(serviceDocuments, this.value);

            if (checklistBtn)  { checklistBtn.style.display  = known ? 'inline-flex' : 'none'; }
            if (checklistInfo) { checklistInfo.style.display = known ? 'block' : 'none'; }
        });
    }

    if (checklistBtn) {
        checklistBtn.addEventListener('click', function () {
            var key = checklistService ? checklistService.value : '';
            var documents = serviceDocuments[key];

            if (!documents) {
                if (checklistInfo) {
                    checklistInfo.textContent = 'Choose a service type first.';
                    checklistInfo.style.display = 'block';
                }
                return;
            }

            var label = checklistService.options[checklistService.selectedIndex].text;
            var lines = [
                'SELAMAWIT H/MARIAM — ACCOUNTING & FINANCIAL CONSULTING',
                'Document checklist',
                '',
                'Service:   ' + label,
                'Generated: ' + new Date().toLocaleDateString(),
                '==================================================',
                '',
                'BRING THE FOLLOWING:',
                ''
            ];

            documents.forEach(function (item) { lines.push('[ ] ' + item); });

            lines.push(
                '',
                '==================================================',
                'Tick each item as you gather it. Documents should be',
                'current and legible. If something on this list does not',
                'apply to you, tell us rather than leaving it blank.',
                '',
                'Questions: +251 933 5831'
            );

            var blob = new Blob([lines.join('\n')], { type: 'text/plain;charset=utf-8' });
            var url  = URL.createObjectURL(blob);
            var link = document.createElement('a');

            link.href = url;
            link.download = 'document-checklist-' + key + '.txt';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        });
    }

    /* ---------- 11. Upload drop zone ---------- */
    /* The real <input type="file"> is still there, hidden but reachable
       by keyboard through its label. Drag and drop is layered on top. */
    var dropZone = $('#dropZone');
    var dropName = $('#dropName');
    var fileInput = $('#document');

    if (dropZone && fileInput) {
        var showName = function () {
            if (!dropName) { return; }

            dropName.textContent = fileInput.files && fileInput.files.length
                ? fileInput.files[0].name
                : 'Choose a file';
        };

        fileInput.addEventListener('change', showName);

        ['dragenter', 'dragover'].forEach(function (type) {
            dropZone.addEventListener(type, function (event) {
                event.preventDefault();
                dropZone.classList.add('is-over');
            });
        });

        ['dragleave', 'drop'].forEach(function (type) {
            dropZone.addEventListener(type, function (event) {
                event.preventDefault();
                dropZone.classList.remove('is-over');
            });
        });

        dropZone.addEventListener('drop', function (event) {
            if (event.dataTransfer && event.dataTransfer.files.length) {
                fileInput.files = event.dataTransfer.files;
                showName();
            }
        });
    }

    /* ---------- 12. Invoice line editor ---------- */
    var lineTable = $('#lineItems');
    var addLine   = $('#addLine');
    var totalsBox = $('#liveTotals');

    if (lineTable) {
        var body = lineTable.querySelector('tbody');

        var recalc = function () {
            if (!totalsBox) { return; }

            var subtotal = 0;

            $$('.lines__row', body).forEach(function (row) {
                var qty   = parseFloat((row.querySelector('[name="quantity[]"]') || {}).value) || 0;
                var price = parseFloat((row.querySelector('[name="unit_price[]"]') || {}).value) || 0;

                subtotal += qty * price;
            });

            var rateInput = $('#tax_rate');
            var rate = rateInput ? (parseFloat(rateInput.value) || 0) : 0;
            var tax  = subtotal * (rate / 100);

            var show = function (key, value) {
                var el = totalsBox.querySelector('[data-total="' + key + '"]');
                if (el) {
                    el.textContent = value.toLocaleString(undefined, {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                }
            };

            show('subtotal', subtotal);
            show('tax', tax);
            show('grand', subtotal + tax);

            var rateLabel = totalsBox.querySelector('[data-total="rate"]');
            if (rateLabel) { rateLabel.textContent = String(rate); }
        };

        // One listener on the table rather than one per input, so rows
        // added later are covered without rebinding anything.
        lineTable.addEventListener('input', recalc);

        var rateField = $('#tax_rate');
        if (rateField) { rateField.addEventListener('input', recalc); }

        lineTable.addEventListener('click', function (event) {
            if (!event.target.classList.contains('lines__remove')) { return; }

            var rows = $$('.lines__row', body);

            if (rows.length === 1) {
                // Never leave the editor with no rows at all — clear the
                // last one instead of deleting it.
                $$('input', rows[0]).forEach(function (input) {
                    input.value = input.name === 'quantity[]' ? '1' : '';
                });
            } else {
                event.target.closest('.lines__row').remove();
            }

            recalc();
        });

        if (addLine) {
            addLine.addEventListener('click', function () {
                var rows = $$('.lines__row', body);
                var fresh = rows[rows.length - 1].cloneNode(true);

                $$('input', fresh).forEach(function (input) {
                    input.value = input.name === 'quantity[]' ? '1' : '';
                });

                body.appendChild(fresh);
                var firstField = fresh.querySelector('input');
                if (firstField) { firstField.focus(); }

                recalc();
            });
        }

        recalc();
    }

    /* ---------- 13. Confirmation prompts ---------- */
    /* Replaces inline onsubmit="return confirm(…)". Those are blocked by
       the Content-Security-Policy, and a blocked handler cannot cancel
       the event — so the form would have submitted without ever asking.
       One delegated listener covers every form on the page, including
       rows added later. */
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form || !form.getAttribute) { return; }

        var message = form.getAttribute('data-confirm');
        if (!message) { return; }

        if (!window.confirm(message)) {
            event.preventDefault();
        }
    });

    /* ---------- 14. Print ---------- */
    document.addEventListener('click', function (event) {
        var trigger = event.target.closest ? event.target.closest('[data-print]') : null;
        if (trigger) { window.print(); }
    });

    /* ---------- 15. Current year in footers ---------- */
    var yearSpan = $('#currentYear');
    if (yearSpan) {
        yearSpan.textContent = String(new Date().getFullYear());
    }
})();
