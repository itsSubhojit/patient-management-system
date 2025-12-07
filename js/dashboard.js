// Bulk select/deselect checkboxes for patients
function toggleSelectAll(source) {
    const checkboxes = document.querySelectorAll('input[name="selected_patients[]"]');
    for (let i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = source.checked;
    }
}
// Patient Management System JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            const bsAlert = new bootstrap.Alert(alert);
            if (bsAlert) {
                bsAlert.close();
            }
        }, 5000);
    });

    // Confirmation dialogs
    const deleteButtons = document.querySelectorAll('[data-confirm]');
    deleteButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            const message = this.getAttribute('data-confirm') || 'Are you sure you want to delete this item?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });

    // Patient search with debouncing
    const searchInput = document.getElementById('patient-search');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            if (query.length >= 3) {
                searchTimeout = setTimeout(() => {
                    searchPatients(query);
                }, 300);
            } else if (query.length === 0) {
                clearSearchResults();
            }
        });
    }

    // Form validation
    const forms = document.querySelectorAll('.needs-validation');
    forms.forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });

    // Date restrictions (no past dates for appointments)
    const appointmentDateInput = document.getElementById('appointment_date');
    if (appointmentDateInput) {
        const today = new Date().toISOString().split('T')[0];
        appointmentDateInput.setAttribute('min', today);
    }

    // Time slot availability check
    const doctorSelect = document.getElementById('doctor_id');
    const dateInput = document.getElementById('appointment_date');
    const timeSelect = document.getElementById('appointment_time');
    
    if (doctorSelect && dateInput && timeSelect) {
        [doctorSelect, dateInput].forEach(element => {
            element.addEventListener('change', function() {
                if (doctorSelect.value && dateInput.value) {
                    checkTimeSlotAvailability();
                }
            });
        });
    }

    // Auto-generate patient ID preview
    const patientForm = document.getElementById('patient-form');
    if (patientForm) {
        const firstNameInput = document.getElementById('first_name');
        const lastNameInput = document.getElementById('last_name');
        const idPreview = document.getElementById('patient-id-preview');
        
        if (firstNameInput && lastNameInput && idPreview) {
            [firstNameInput, lastNameInput].forEach(input => {
                input.addEventListener('input', function() {
                    updatePatientIdPreview();
                });
            });
        }
    }

    // Sidebar toggle for mobile
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');
        });
        
        // Close sidebar when clicking outside
        document.addEventListener('click', function(event) {
            if (!sidebar.contains(event.target) && !sidebarToggle.contains(event.target)) {
                sidebar.classList.remove('show');
            }
        });
    }
});

// Search patients function
function searchPatients(query) {
    const searchResults = document.getElementById('search-results');
    if (!searchResults) return;

    // Show loading state
    searchResults.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Searching...</div>';
    searchResults.style.display = 'block';

    // Simulate API call (replace with actual AJAX call)
    setTimeout(() => {
        // This would be replaced with actual search results from server
        const mockResults = [
            { id: 1, name: 'John Doe', patient_id: 'PAT000001', phone: '123-456-7890' },
            { id: 2, name: 'Jane Smith', patient_id: 'PAT000002', phone: '098-765-4321' }
        ];

        if (mockResults.length > 0) {
            let resultsHtml = '<div class="list-group">';
            mockResults.forEach(patient => {
                resultsHtml += `
                    <a href="patients.php?action=view&id=${patient.id}" class="list-group-item list-group-item-action">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">${patient.name}</h6>
                                <small class="text-muted">${patient.patient_id} • ${patient.phone}</small>
                            </div>
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </a>
                `;
            });
            resultsHtml += '</div>';
            searchResults.innerHTML = resultsHtml;
        } else {
            searchResults.innerHTML = '<div class="text-center text-muted">No patients found</div>';
        }
    }, 500);
}

// Clear search results
function clearSearchResults() {
    const searchResults = document.getElementById('search-results');
    if (searchResults) {
        searchResults.style.display = 'none';
    }
}

// Check time slot availability
function checkTimeSlotAvailability() {
    const doctorId = document.getElementById('doctor_id').value;
    const date = document.getElementById('appointment_date').value;
    const timeSelect = document.getElementById('appointment_time');
    
    if (!doctorId || !date) return;

    // Disable time select while checking
    timeSelect.disabled = true;
    
    // Simulate availability check (replace with actual AJAX call)
    setTimeout(() => {
        // This would check against actual database
        const unavailableTimes = ['10:00', '14:00', '16:30']; // Mock unavailable times
        
        Array.from(timeSelect.options).forEach(option => {
            if (unavailableTimes.includes(option.value)) {
                option.disabled = true;
                option.text = option.text + ' (Unavailable)';
            } else {
                option.disabled = false;
                option.text = option.text.replace(' (Unavailable)', '');
            }
        });
        
        timeSelect.disabled = false;
    }, 1000);
}

// Update patient ID preview
function updatePatientIdPreview() {
    const firstName = document.getElementById('first_name').value;
    const lastName = document.getElementById('last_name').value;
    const preview = document.getElementById('patient-id-preview');
    
    if (firstName || lastName) {
        // Generate preview ID (this would be handled by server)
        const initials = (firstName.charAt(0) + lastName.charAt(0)).toUpperCase();
        const timestamp = Date.now().toString().slice(-4);
        preview.textContent = `Preview: PAT${timestamp}`;
        preview.style.display = 'block';
    } else {
        preview.style.display = 'none';
    }
}

// Utility functions
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(amount);
}

function formatDate(dateString) {
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return new Date(dateString).toLocaleDateString('en-US', options);
}

function formatTime(timeString) {
    const options = { hour: 'numeric', minute: '2-digit', hour12: true };
    return new Date('2000-01-01 ' + timeString).toLocaleTimeString('en-US', options);
}

// Export functions for use in other scripts
window.PMS = {
    searchPatients,
    clearSearchResults,
    checkTimeSlotAvailability,
    updatePatientIdPreview,
    formatCurrency,
    formatDate,
    formatTime
};

// Print functionality
function printElement(elementId) {
    const element = document.getElementById(elementId);
    if (!element) return;

    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Print</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <link href="css/style.css" rel="stylesheet">
            <style>
                body { margin: 20px; }
                @media print {
                    .no-print { display: none !important; }
                }
            </style>
        </head>
        <body>
            ${element.outerHTML}
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}

// Dashboard chart initialization (if Chart.js is loaded)
function initializeDashboardCharts() {
    if (typeof Chart === 'undefined') return;

    // Appointments chart
    const appointmentsCtx = document.getElementById('appointmentsChart');
    if (appointmentsCtx) {
        new Chart(appointmentsCtx, {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Appointments',
                    data: [12, 19, 3, 5, 2, 3, 10],
                    borderColor: 'rgb(75, 192, 192)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    // Patient demographics chart
    const demographicsCtx = document.getElementById('demographicsChart');
    if (demographicsCtx) {
        new Chart(demographicsCtx, {
            type: 'doughnut',
            data: {
                labels: ['Male', 'Female', 'Other'],
                datasets: [{
                    data: [45, 52, 3],
                    backgroundColor: [
                        'rgb(54, 162, 235)',
                        'rgb(255, 99, 132)',
                        'rgb(255, 205, 86)'
                    ]
                }]
            },
            options: {
                responsive: true
            }
        });
    }
}

// Initialize charts when page loads
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(initializeDashboardCharts, 100);
});