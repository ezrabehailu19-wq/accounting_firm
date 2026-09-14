// ----- 1. Header scroll effect -----
var header = document.getElementById('header');
if (header) {
    window.addEventListener('scroll', function () {
        if (window.pageYOffset > 50) {
            header.classList.add('scrolled');
        } else {
            header.classList.remove('scrolled');
        }
    });
}

// ----- 2. Scroll-to-top button -----
var scrollTopBtn = document.getElementById('scrollTop');
if (scrollTopBtn) {
    window.addEventListener('scroll', function () {
        if (window.pageYOffset > 500) {
            scrollTopBtn.classList.add('visible');
        } else {
            scrollTopBtn.classList.remove('visible');
        }
    });
    scrollTopBtn.addEventListener('click', function () {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
}

// ----- 3. Mobile menu -----
var menuToggle = document.getElementById('menuToggle');
var navMenu = document.getElementById('navMenu');
var navLinks = document.querySelectorAll('.nav-link');

if (menuToggle && navMenu) {
    menuToggle.addEventListener('click', function () {
        menuToggle.classList.toggle('active');
        navMenu.classList.toggle('active');
    });
}

if (navLinks.length > 0) {
    for (var i = 0; i < navLinks.length; i++) {
        navLinks[i].addEventListener('click', function () {
            if (menuToggle) menuToggle.classList.remove('active');
            if (navMenu) navMenu.classList.remove('active');
        });
    }
}

// ----- 4. Statistics counter (index.html only) -----
var statNumbers = document.querySelectorAll('.stat-number');
var statsSection = document.querySelector('.stats-section');
var statsAnimated = false;

function animateStats() {
    if (statsAnimated || !statsSection || statNumbers.length === 0) return;

    var rect = statsSection.getBoundingClientRect();
    var isVisible = rect.top < window.innerHeight && rect.bottom > 0;

    if (isVisible) {
        statsAnimated = true;
        for (var i = 0; i < statNumbers.length; i++) {
            (function (el) {
                var target = parseInt(el.getAttribute('data-target'), 10);
                var duration = 1500;
                var step = target / (duration / 16);
                var current = 0;
                function update() {
                    current += step;
                    if (current < target) {
                        el.textContent = Math.floor(current);
                        requestAnimationFrame(update);
                    } else {
                        el.textContent = target;
                    }
                }
                requestAnimationFrame(update);
            })(statNumbers[i]);
        }
    }
}

if (statsSection) {
    window.addEventListener('scroll', animateStats);
}

// ----- 5. Service search & filter (services.html only) -----
var serviceSearch = document.getElementById('serviceSearch');
var servicesGrid = document.getElementById('servicesGrid');
var filterBtns = document.querySelectorAll('.filter-btn');

function filterServices() {
    if (!servicesGrid) return;

    var searchTerm = serviceSearch ? serviceSearch.value.toLowerCase() : '';
    var activeBtn = document.querySelector('.filter-btn.active');
    var filterValue = activeBtn ? activeBtn.getAttribute('data-filter') : 'all';
    var cards = servicesGrid.querySelectorAll('.service-card');
    var i;

    for (i = 0; i < cards.length; i++) {
        var text = cards[i].textContent.toLowerCase();
        var category = cards[i].getAttribute('data-category');
        var matchesSearch = text.indexOf(searchTerm) !== -1;
        var matchesFilter = filterValue === 'all' || category === filterValue;

        if (matchesSearch && matchesFilter) {
            cards[i].style.display = 'block';
        } else {
            cards[i].style.display = 'none';
        }
    }
}

if (serviceSearch) {
    serviceSearch.addEventListener('input', filterServices);
}

for (var f = 0; f < filterBtns.length; f++) {
    filterBtns[f].addEventListener('click', function () {
        for (var b = 0; b < filterBtns.length; b++) {
            filterBtns[b].classList.remove('active');
        }
        this.classList.add('active');
        filterServices();
    });
}

// ----- 6. FAQ accordion (faq.html only) -----
var faqItems = document.querySelectorAll('.faq-item');

for (var q = 0; q < faqItems.length; q++) {
    (function (item) {
        var question = item.querySelector('.faq-question');
        if (question) {
            question.addEventListener('click', function () {
                var isActive = item.classList.contains('active');
                for (var a = 0; a < faqItems.length; a++) {
                    faqItems[a].classList.remove('active');
                }
                if (!isActive) {
                    item.classList.add('active');
                }
            });
        }
    })(faqItems[q]);
}

// ----- 7. Contact form & modal (contact.html only) -----
var contactForm = document.getElementById('contactForm');
var successModal = document.getElementById('successModal');
var closeModal = document.getElementById('closeModal');

// Fetch a CSRF token once when the page loads, since contact.html is static
// and can't render one server-side. Reused for every submit attempt.
var csrfToken = '';
if (contactForm) {
    fetch('csrf_token.php')
        .then(function (response) { return response.json(); })
        .then(function (data) { csrfToken = data.token || ''; })
        .catch(function () { /* token stays empty; server will reject submit */ });
}

if (contactForm) {
    contactForm.addEventListener('submit', function (e) {
        e.preventDefault();

        var fullname = document.getElementById('fullname');
        var email = document.getElementById('email');
        var message = document.getElementById('message');
        var isValid = true;

        // Reset errors
        var errEls = document.querySelectorAll('.error-message');
        for (var r = 0; r < errEls.length; r++) {
            errEls[r].textContent = '';
        }
        var inputs = document.querySelectorAll('.form-group input, .form-group textarea');
        for (var r = 0; r < inputs.length; r++) {
            inputs[r].classList.remove('error');
        }

        // Validate name
        if (fullname.value.trim().length < 2) {
            document.getElementById('nameError').textContent = 'Please enter your full name';
            fullname.classList.add('error');
            isValid = false;
        }

        // Validate email
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email.value)) {
            document.getElementById('emailError').textContent = 'Please enter a valid email address';
            email.classList.add('error');
            isValid = false;
        }

        // Validate message
        if (message.value.trim().length < 10) {
            document.getElementById('messageError').textContent = 'Message must be at least 10 characters';
            message.classList.add('error');
            isValid = false;
        }
// success message send to php first  then show the modal
        if (isValid) {
    var formData = new FormData(contactForm);
    formData.append('csrf_token', csrfToken);

    fetch('contact_submit.php', {
        method: 'POST',
        body: formData
    })
    .then(function(response) {
        return response.json();
    })
    .then(function(data) {
        if (data.success && successModal) {
            successModal.classList.add('active');
            contactForm.reset();
        } else if (!data.success) {
            var fieldErrorMap = {
                fullname: 'nameError',
                email: 'emailError',
                message: 'messageError'
            };
            var shownFieldError = false;
            if (data.errors) {
                for (var field in data.errors) {
                    if (fieldErrorMap[field]) {
                        var el = document.getElementById(fieldErrorMap[field]);
                        if (el) {
                            el.textContent = data.errors[field];
                            shownFieldError = true;
                        }
                    }
                }
            }
            if (!shownFieldError) {
                alert(data.error || 'Something went wrong. Please try again.');
            }
        }
    })
    .catch(function(error) {
        alert('Something went wrong. Please try again.');
    });
}
    });
}

if (closeModal) {
    closeModal.addEventListener('click', function () {
        if (successModal) successModal.classList.remove('active');
    });
}

if (successModal) {
    successModal.addEventListener('click', function (e) {
        if (e.target === successModal) {
            successModal.classList.remove('active');
        }
    });
}

// ----- 8. Service Cost Estimator (services.html only) -----
var serviceType = document.getElementById('serviceType');
var serviceDuration = document.getElementById('serviceDuration');
var calculateBtn = document.getElementById('calculateBtn');
var totalAmount = document.getElementById('totalAmount');
var estimateResult = document.getElementById('estimateResult');

function calculateTotal() {
    var basePrice = 0;
    var duration = parseInt(serviceDuration ? serviceDuration.value : 1) || 1;
    var extrasTotal = 0;
    
    // Get base price from selected service
    if (serviceType && serviceType.value) {
        var selectedOption = serviceType.options[serviceType.selectedIndex];
        basePrice = parseInt(selectedOption.getAttribute('data-price')) || 0;
    }
    
    // Validate that a service is selected
    if (!basePrice) {
        if (totalAmount) {
            totalAmount.textContent = 'Please select a service first';
        }
        if (estimateResult) {
            estimateResult.style.display = 'block';
        }
        return;
    }
    
    // Calculate extras (these are one-time fees, not multiplied by months)
    var checkboxes = document.querySelectorAll('.checkbox-group input[type="checkbox"]');
    for (var i = 0; i < checkboxes.length; i++) {
        if (checkboxes[i].checked) {
            extrasTotal += parseInt(checkboxes[i].getAttribute('data-price')) || 0;
        }
    }
    
    // Calculate total: (monthly price × months) + one-time extras
    var monthlyTotal = basePrice * duration;
    var total = monthlyTotal + extrasTotal;
    
    // Display result with clean total only
    if (totalAmount) {
        totalAmount.textContent = total.toLocaleString() + ' ETB';
    }
    
    if (estimateResult) {
        estimateResult.style.display = 'block';
    }
}

if (calculateBtn) {
    calculateBtn.addEventListener('click', calculateTotal);
}

// Remove auto-calculation on input changes - only calculate on button click

// ----- 9. Service Checklist Downloader (services.html only) -----
var checklistService = document.getElementById('checklistService');
var downloadChecklistBtn = document.getElementById('downloadChecklistBtn');
var checklistInfo = document.getElementById('checklistInfo');

// Document requirements for each service type
var serviceDocuments = {
    tax: [
        'Valid ID (Passport/National ID)',
        'Tax Identification Number (TIN)',
        'Business License (for businesses)',
        'Previous Tax Returns (3 years)',
        'Income Statements (P60, payslips)',
        'Bank Statements (12 months)',
        'Investment Income Statements',
        'Property Ownership Documents',
        'Business Expense Records',
        'VAT Registration Certificate (if applicable)'
    ],
    bookkeeping: [
        'Valid ID (Passport/National ID)',
        'Business License',
        'Bank Statements (6 months)',
        'Sales Invoices and Receipts',
        'Purchase Invoices and Bills',
        'Expense Reports',
        'Previous Accounting Records',
        'Loan Agreements and Statements',
        'Asset Purchase Documents',
        'Inventory Records (if applicable)'
    ],
    payroll: [
        'Valid ID (Passport/National ID)',
        'Business License',
        'Employee Records and Contracts',
        'Previous Payroll Records',
        'Tax Registration Certificates',
        'Pension Registration Documents',
        'Employee Bank Account Details',
        'Leave and Absence Records',
        'Overtime Calculation Records',
        'Benefits and Deduction Documents'
    ],
    advisory: [
        'Valid ID (Passport/National ID)',
        'Business License',
        'Current Financial Statements',
        'Business Plan Documents',
        'Loan Agreements and Statements',
        'Cash Flow Statements',
        'Budget vs Actual Reports',
        'Market Research Data',
        'Competitor Analysis',
        'Growth Projections and Forecasts'
    ],
    audit: [
        'Valid ID (Passport/National ID)',
        'Business License',
        'Previous Audit Reports',
        'Current Financial Statements',
        'General Ledger Details',
        'Bank Reconciliation Statements',
        'Inventory Valuation Reports',
        'Fixed Asset Registers',
        'Tax Clearance Certificates',
        'Board Meeting Minutes',
        'Legal Agreements and Contracts'
    ]
};

function showDownloadButton(serviceType) {
    if (!serviceType || !serviceDocuments[serviceType]) {
        if (downloadChecklistBtn) downloadChecklistBtn.style.display = 'none';
        if (checklistInfo) checklistInfo.style.display = 'none';
        return;
    }

    if (downloadChecklistBtn) downloadChecklistBtn.style.display = 'inline-block';
    if (checklistInfo) checklistInfo.style.display = 'block';
}

function downloadChecklist() {
    var selectedService = checklistService ? checklistService.value : '';
    if (!selectedService) {
        alert('Please select a service type first');
        return;
    }
    
    var serviceName = selectedService ? checklistService.options[checklistService.selectedIndex].text : 'General Service';
    var documents = serviceDocuments[selectedService];
    
    if (!documents || documents.length === 0) {
        alert('No checklist items found for this service.');
        return;
    }
    
    var checklistText = 'SELAMAWIT H/MARIAM ACCOUNTING - DOCUMENT CHECKLIST\n';
    checklistText += 'Generated on: ' + new Date().toLocaleDateString() + '\n';
    checklistText += 'Service Type: ' + serviceName + '\n';
    checklistText += '=============================================\n\n';
    checklistText += 'REQUIRED DOCUMENTS:\n\n';
    
    for (var i = 0; i < documents.length; i++) {
        checklistText += '[ ] ' + documents[i] + '\n';
    }
    
    checklistText += '\n=============================================\n';
    checklistText += 'INSTRUCTIONS:\n';
    checklistText += '☐ Check each box as you gather the documents\n';
    checklistText += '☐ Ensure all documents are recent and valid\n';
    checklistText += '☐ Contact us if you need assistance with any document\n\n';
    checklistText += '=============================================\n';
    checklistText += 'Contact us for assistance with document preparation.\n';
    checklistText += 'Email: selam.acc22@gmail.com | Phone: +251-933-5831\n';
    
    // Create and download file
    var blob = new Blob([checklistText], { type: 'text/plain' });
    var url = window.URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = 'accounting_document_checklist_' + selectedService + '.txt';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

// Add event listener for service selection
if (checklistService) {
    checklistService.addEventListener('change', function() {
        showDownloadButton(this.value);
    });
}

if (downloadChecklistBtn) {
    downloadChecklistBtn.addEventListener('click', downloadChecklist);
}

// ============================================
// SERVICES PAGE JAVASCRIPT FUNCTIONALITY
// ============================================

// ----- SERVICES PAGE SPECIFIC FUNCTIONALITY -----
document.addEventListener('DOMContentLoaded', function() {
    
    // Only run on services page
    if (window.location.pathname.includes('services.html') || window.location.pathname.endsWith('/services')) {
        
        // ===== SERVICE SEARCH AND FILTER =====
        const serviceSearch = document.getElementById('serviceSearch');
        const filterButtons = document.querySelectorAll('.filter-btn');
        const serviceCards = document.querySelectorAll('.service-card');
        
        // Service search functionality
        if (serviceSearch) {
            serviceSearch.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                filterServices(searchTerm, getActiveFilter());
            });
        }
        
        // Filter button functionality
        filterButtons.forEach(button => {
            button.addEventListener('click', function() {
                // Remove active class from all buttons
                filterButtons.forEach(btn => btn.classList.remove('active'));
                // Add active class to clicked button
                this.classList.add('active');
                
                const filter = this.getAttribute('data-filter');
                filterServices(serviceSearch ? serviceSearch.value.toLowerCase() : '', filter);
            });
        });
        
        function getActiveFilter() {
            const activeButton = document.querySelector('.filter-btn.active');
            return activeButton ? activeButton.getAttribute('data-filter') : 'all';
        }
        
        function filterServices(searchTerm, category) {
            serviceCards.forEach(card => {
                const cardCategory = card.getAttribute('data-category');
                const cardTitle = card.querySelector('h3').textContent.toLowerCase();
                const cardDescription = card.querySelector('p').textContent.toLowerCase();
                
                const matchesSearch = searchTerm === '' || 
                    cardTitle.includes(searchTerm) || 
                    cardDescription.includes(searchTerm);
                
                const matchesCategory = category === 'all' || cardCategory === category;
                
                if (matchesSearch && matchesCategory) {
                    card.style.display = 'block';
                    // Add fade-in animation
                    card.style.animation = 'fadeIn 0.3s ease-in';
                } else {
                    card.style.display = 'none';
                }
            });
            
            // Show no results message if needed
            const visibleCards = Array.from(serviceCards).filter(card => card.style.display !== 'none');
            const noResultsMessage = document.getElementById('noResults');
            
            if (visibleCards.length === 0 && !noResultsMessage) {
                const message = document.createElement('div');
                message.id = 'noResults';
                message.className = 'no-results';
                message.innerHTML = `
                    <div class="no-results-icon">🔍</div>
                    <h3>No services found</h3>
                    <p>Try adjusting your search or filter criteria</p>
                `;
                document.getElementById('servicesGrid').appendChild(message);
            } else if (visibleCards.length > 0 && noResultsMessage) {
                noResultsMessage.remove();
            }
        }
        
        // ===== COST ESTIMATOR FUNCTIONALITY =====
        const serviceTypeSelect = document.getElementById('serviceType');
        const serviceDurationInput = document.getElementById('serviceDuration');
        const calculateBtn = document.getElementById('calculateBtn');
        const estimateResult = document.getElementById('estimateResult');
        const totalAmountElement = document.getElementById('totalAmount');
        
        // Additional service checkboxes
        const prioritySupportCheckbox = document.getElementById('prioritySupport');
        const detailedReportsCheckbox = document.getElementById('detailedReports');
        const consultationCheckbox = document.getElementById('consultation');
        
        if (calculateBtn) {
            calculateBtn.addEventListener('click', calculateTotal);
        }
        
        // Auto-calculate when inputs change
        [serviceTypeSelect, serviceDurationInput, prioritySupportCheckbox, detailedReportsCheckbox, consultationCheckbox].forEach(element => {
            if (element) {
                element.addEventListener('change', calculateTotal);
            }
        });
        
        function calculateTotal() {
            let total = 0;
            
            // Get base service price
            if (serviceTypeSelect && serviceTypeSelect.value) {
                const selectedOption = serviceTypeSelect.options[serviceTypeSelect.selectedIndex];
                const basePrice = parseInt(selectedOption.getAttribute('data-price')) || 0;
                const duration = parseInt(serviceDurationInput.value) || 1;
                total = basePrice * duration;
            }
            
            // Add additional services
            if (prioritySupportCheckbox && prioritySupportCheckbox.checked) {
                total += parseInt(prioritySupportCheckbox.getAttribute('data-price')) || 0;
            }
            if (detailedReportsCheckbox && detailedReportsCheckbox.checked) {
                total += parseInt(detailedReportsCheckbox.getAttribute('data-price')) || 0;
            }
            if (consultationCheckbox && consultationCheckbox.checked) {
                total += parseInt(consultationCheckbox.getAttribute('data-price')) || 0;
            }
            
            // Display result
            if (total > 0 && estimateResult && totalAmountElement) {
                totalAmountElement.textContent = formatCurrency(total);
                estimateResult.style.display = 'block';
                estimateResult.style.animation = 'fadeIn 0.3s ease-in';
                
                // Add animation to the amount
                totalAmountElement.style.animation = 'pulse 0.5s ease-in-out';
            } else if (estimateResult) {
                estimateResult.style.display = 'none';
            }
        }
        
        function formatCurrency(amount) {
            return new Intl.NumberFormat('en-ET', {
                style: 'currency',
                currency: 'ETB',
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(amount).replace('ETB', 'ETB');
        }
        
        // ===== DOCUMENT CHECKLIST FUNCTIONALITY =====
        const checklistServiceSelect = document.getElementById('checklistService');
        const downloadChecklistBtn = document.getElementById('downloadChecklistBtn');
        const checklistInfo = document.getElementById('checklistInfo');
        
        if (checklistServiceSelect) {
            checklistServiceSelect.addEventListener('change', function() {
                if (this.value) {
                    if (downloadChecklistBtn) downloadChecklistBtn.style.display = 'block';
                    if (checklistInfo) checklistInfo.style.display = 'block';
                } else {
                    if (downloadChecklistBtn) downloadChecklistBtn.style.display = 'none';
                    if (checklistInfo) checklistInfo.style.display = 'none';
                }
            });
        }
        
        if (downloadChecklistBtn) {
            downloadChecklistBtn.addEventListener('click', generateChecklist);
        }
        
        function generateChecklist() {
            const selectedService = checklistServiceSelect.value;
            if (!selectedService) return;
            
            const checklists = {
                tax: {
                    title: 'Tax Filing & Compliance Document Checklist',
                    documents: [
                        'Business Registration Certificate',
                        'Tax Identification Number (TIN)',
                        'Previous Year Tax Returns',
                        'Financial Statements (P&L, Balance Sheet)',
                        'Bank Statements (12 months)',
                        'Expense Receipts and Invoices',
                        'Asset Purchase Documentation',
                        'Employee Payroll Records',
                        'VAT Registration Certificate',
                        'Customs Documents (if applicable)'
                    ]
                },
                bookkeeping: {
                    title: 'Bookkeeping Services Document Checklist',
                    documents: [
                        'Business License',
                        'Bank Account Statements',
                        'Sales Invoices and Receipts',
                        'Purchase Orders and Bills',
                        'Expense Reports',
                        'Credit Card Statements',
                        'Loan Documents',
                        'Asset Depreciation Schedules',
                        'Inventory Records',
                        'Petty Cash Records'
                    ]
                },
                payroll: {
                    title: 'Payroll Management Document Checklist',
                    documents: [
                        'Employee Contracts and Agreements',
                        'Employee Tax Forms',
                        'Bank Account Details',
                        'Attendance Records',
                        'Overtime Approval Forms',
                        'Leave Balance Records',
                        'Deduction Authorization Forms',
                        'Previous Payroll Registers',
                        'Social Security Registration',
                        'Pension Fund Documents'
                    ]
                },
                advisory: {
                    title: 'Financial Advisory Document Checklist',
                    documents: [
                        'Business Plan',
                        'Current Financial Statements',
                        'Budget vs Actual Reports',
                        'Cash Flow Statements',
                        'Market Analysis Reports',
                        'Competitor Analysis',
                        'Growth Projections',
                        'Investment Documentation',
                        'Loan Agreements',
                        'Strategic Planning Documents'
                    ]
                },
                audit: {
                    title: 'Audit & Assurance Document Checklist',
                    documents: [
                        'Financial Statements (3 years)',
                        'General Ledger Details',
                        'Bank Reconciliations',
                        'Fixed Asset Register',
                        'Inventory Valuation Reports',
                        'Debtors and Creditors Aging',
                        'Tax Clearance Certificates',
                        'Regulatory Compliance Reports',
                        'Internal Control Documentation',
                        'Previous Audit Reports'
                    ]
                }
            };
            
            const checklist = checklists[selectedService];
            if (!checklist) return;
            
            // Generate downloadable checklist
            generateDownloadableChecklist(checklist);
        }
        
        function generateDownloadableChecklist(checklist) {
            // Create checklist content
            let content = `
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>${checklist.title}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        h1 { color: #1e3a8a; text-align: center; border-bottom: 2px solid #1e3a8a; padding-bottom: 10px; }
        h2 { color: #1e3a8a; margin-top: 30px; }
        .checklist-item { margin: 10px 0; display: flex; align-items: center; }
        .checkbox { width: 20px; height: 20px; border: 2px solid #1e3a8a; margin-right: 15px; display: inline-block; }
        .notes { background: #f5f7fa; padding: 15px; border-radius: 5px; margin-top: 20px; }
        .footer { margin-top: 40px; text-align: center; color: #666; font-size: 12px; }
        @media print { .notes { page-break-inside: avoid; } }
    </style>
</head>
<body>
    <h1>${checklist.title}</h1>
    <p><strong>Generated:</strong> ${new Date().toLocaleDateString()}</p>
    <p><strong>Company:</strong> _____________________</p>
    <p><strong>Contact Person:</strong> _____________________</p>
    <p><strong>Phone:</strong> _____________________</p>
    
    <h2>Required Documents Checklist</h2>
`;
            
            checklist.documents.forEach((doc, index) => {
                content += `
    <div class="checklist-item">
        <div class="checkbox"></div>
        <span>${index + 1}. ${doc}</span>
    </div>`;
            });
            
            content += `
    <div class="notes">
        <h3>Important Notes:</h3>
        <ul>
            <li>Please ensure all documents are recent and valid</li>
            <li>Make copies of all original documents</li>
            <li>Organize documents in the order listed above</li>
            <li>Contact our office if you need clarification on any item</li>
        </ul>
    </div>
    
    <div class="footer">
        <p>Selamawit H/Mariam Accounting & Financial Consulting</p>
        <p>Phone: +251-XXX-XXXX | Email: info@selamawit-accounting.com</p>
        <p>Generated on ${new Date().toLocaleDateString()}</p>
    </div>
</body>
</html>`;
            
            // Create and download file
            const blob = new Blob([content], { type: 'text/html' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `${checklist.title.replace(/[^a-z0-9]/gi, '_').toLowerCase()}_${Date.now()}.html`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            
            // Show success message
            showNotification('Checklist downloaded successfully!', 'success');
        }
        
        // ===== UTILITY FUNCTIONS =====
        function showNotification(message, type = 'info') {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.innerHTML = `
                <div class="notification-content">
                    <span class="notification-message">${message}</span>
                    <button class="notification-close">&times;</button>
                </div>
            `;
            
            // Add styles
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#1e3a8a'};
                color: white;
                padding: 15px 20px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 10000;
                max-width: 300px;
                animation: slideInRight 0.3s ease-out;
            `;
            
            // Add to page
            document.body.appendChild(notification);
            
            // Close button functionality
            const closeBtn = notification.querySelector('.notification-close');
            closeBtn.style.cssText = `
                background: none;
                border: none;
                color: white;
                font-size: 20px;
                cursor: pointer;
                margin-left: 10px;
            `;
            
            closeBtn.addEventListener('click', () => {
                notification.remove();
            });
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 5000);
        }
        
        // ===== ADD CSS ANIMATIONS =====
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(10px); }
                to { opacity: 1; transform: translateY(0); }
            }
            
            @keyframes slideInRight {
                from { opacity: 0; transform: translateX(100px); }
                to { opacity: 1; transform: translateX(0); }
            }
            
            @keyframes pulse {
                0%, 100% { transform: scale(1); }
                50% { transform: scale(1.05); }
            }
            
            .no-results {
                text-align: center;
                padding: 60px 20px;
                color: var(--gray-600);
            }
            
            .no-results-icon {
                font-size: 4rem;
                margin-bottom: 1rem;
                opacity: 0.5;
            }
            
            .no-results h3 {
                color: var(--gray-700);
                margin-bottom: 0.5rem;
            }
            
            .notification-content {
                display: flex;
                align-items: center;
                justify-content: space-between;
            }
        `;
        document.head.appendChild(style);
        
        // ===== INITIALIZATION =====
        // Initialize with all services visible
        filterServices('', 'all');
    }
});

// ----- 10. Current year in footer -----
var currentYearSpan = document.getElementById('currentYear');
if (currentYearSpan) {
    currentYearSpan.textContent = new Date().getFullYear();
}