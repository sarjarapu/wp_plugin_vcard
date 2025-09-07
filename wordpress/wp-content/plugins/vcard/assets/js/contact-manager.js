/**
 * vCard Contact Manager - Local Storage System
 * Handles contact saving, retrieval, and management for anonymous users
 * Version: 1.0.0
 */

(function($) {
    'use strict';

    // Contact Manager object
    var VCardContactManager = {
        
        // Storage key for local storage
        storageKey: 'vcard_saved_contacts',
        
        // Storage key for contact data
        contactDataKey: 'vcard_contact_data',
        
        // Initialize contact manager
        init: function() {
            this.bindEvents();
            this.initContactList();
            this.checkStorageSupport();
        },

        /**
         * Check if local storage is supported
         */
        checkStorageSupport: function() {
            try {
                var test = 'test';
                localStorage.setItem(test, test);
                localStorage.removeItem(test);
                return true;
            } catch(e) {
                console.warn('Local storage not supported');
                this.showMessage('Local storage not supported. Contacts cannot be saved.', 'warning');
                return false;
            }
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Save contact button
            $(document).on('click', '.vcard-save-contact-btn', this.handleSaveContact.bind(this));
            
            // Remove contact button
            $(document).on('click', '.vcard-remove-contact-btn', this.handleRemoveContact.bind(this));
            
            // View saved contacts
            $(document).on('click', '.vcard-view-contacts-btn', this.showContactList.bind(this));
            
            // Export contacts
            $(document).on('click', '.vcard-export-contacts-btn', this.handleExportContacts.bind(this));
            
            // Search contacts
            $(document).on('input', '.vcard-contact-search', this.handleContactSearch.bind(this));
            
            // Filter contacts
            $(document).on('change', '.vcard-contact-filter', this.handleContactFilter.bind(this));
            
            // Clear all contacts
            $(document).on('click', '.vcard-clear-contacts-btn', this.handleClearContacts.bind(this));
            
            // Cloud sync
            $(document).on('click', '.vcard-sync-cloud-btn', this.handleSyncCloud.bind(this));
            
            // Register for sync
            $(document).on('click', '.vcard-register-for-sync-btn', this.handleRegisterForSync.bind(this));
            
            // Contact list modal close
            $(document).on('click', '.vcard-modal-close, .vcard-modal-overlay', this.hideContactList.bind(this));
        },

        /**
         * Handle save contact
         */
        handleSaveContact: function(e) {
            e.preventDefault();
            
            var $button = $(e.currentTarget);
            var profileId = $button.data('profile-id');
            
            if (!profileId) {
                this.showMessage('Profile ID not found', 'error');
                return;
            }

            // Check if already saved
            if (this.isContactSaved(profileId)) {
                this.showMessage('Contact already saved', 'info');
                return;
            }

            // Show loading state
            $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');
            
            // Get profile data from the page
            var contactData = this.extractContactData(profileId);
            
            if (contactData) {
                // Save to local storage first
                this.saveContact(profileId, contactData);
                
                // If user is logged in, also save to cloud
                if (vcard_contact_manager && vcard_contact_manager.is_logged_in) {
                    this.saveToCloud(profileId, contactData);
                }
                
                // Update button state
                $button.html('<i class="fas fa-check"></i> Saved!')
                       .addClass('saved')
                       .removeClass('vcard-save-contact-btn')
                       .addClass('vcard-remove-contact-btn');
                
                this.showMessage('Contact saved successfully!', 'success');
                
                // Update contact count
                this.updateContactCount();
                
                // Show registration prompt for anonymous users
                if (!vcard_contact_manager || !vcard_contact_manager.is_logged_in) {
                    this.showRegistrationPrompt();
                }
                
            } else {
                this.showMessage('Failed to extract contact data', 'error');
                $button.prop('disabled', false).html('<i class="fas fa-bookmark"></i> Save Contact');
            }
        },

        /**
         * Handle remove contact
         */
        handleRemoveContact: function(e) {
            e.preventDefault();
            
            var $button = $(e.currentTarget);
            var profileId = $button.data('profile-id');
            
            if (!profileId) {
                this.showMessage('Profile ID not found', 'error');
                return;
            }

            // Remove from local storage
            this.removeContact(profileId);
            
            // Update button state
            $button.html('<i class="fas fa-bookmark"></i> Save Contact')
                   .removeClass('saved')
                   .removeClass('vcard-remove-contact-btn')
                   .addClass('vcard-save-contact-btn')
                   .prop('disabled', false);
            
            this.showMessage('Contact removed', 'info');
            
            // Update contact count
            this.updateContactCount();
            
            // Refresh contact list if open
            if ($('.vcard-contact-list-modal').is(':visible')) {
                this.renderContactList();
            }
        },

        /**
         * Extract contact data from the current page
         */
        extractContactData: function(profileId) {
            var contactData = {
                id: profileId,
                saved_at: new Date().toISOString(),
                business_name: '',
                owner_name: '',
                job_title: '',
                phone: '',
                email: '',
                website: '',
                address: '',
                business_description: '',
                profile_url: window.location.href,
                template_name: '',
                logo_url: ''
            };

            // Extract data from meta tags or page elements
            contactData.business_name = $('meta[name="vcard:business_name"]').attr('content') || 
                                       $('.vcard-business-name').text() || 
                                       $('.business-name').text() || 
                                       $('h1').first().text();

            contactData.owner_name = $('meta[name="vcard:owner_name"]').attr('content') || 
                                    $('.vcard-owner-name').text() || 
                                    $('.owner-name').text();

            contactData.job_title = $('meta[name="vcard:job_title"]').attr('content') || 
                                   $('.vcard-job-title').text() || 
                                   $('.job-title').text();

            contactData.phone = $('meta[name="vcard:phone"]').attr('content') || 
                               $('.vcard-phone').text() || 
                               $('a[href^="tel:"]').first().text();

            contactData.email = $('meta[name="vcard:email"]').attr('content') || 
                               $('.vcard-email').text() || 
                               $('a[href^="mailto:"]').first().text();

            contactData.website = $('meta[name="vcard:website"]').attr('content') || 
                                 $('.vcard-website').attr('href') || 
                                 $('.website-link').attr('href');

            contactData.address = $('meta[name="vcard:address"]').attr('content') || 
                                 $('.vcard-address').text() || 
                                 $('.address').text();

            contactData.business_description = $('meta[name="description"]').attr('content') || 
                                              $('.vcard-description').text() || 
                                              $('.business-description').text();

            contactData.template_name = $('body').attr('class').match(/template-(\w+)/)?.[1] || 'default';

            contactData.logo_url = $('.vcard-logo img').attr('src') || 
                                  $('.business-logo img').attr('src') || 
                                  $('.logo img').attr('src');

            // Validate required fields
            if (!contactData.business_name && !contactData.owner_name) {
                return null;
            }

            return contactData;
        },

        /**
         * Save contact to local storage
         */
        saveContact: function(profileId, contactData) {
            try {
                // Get existing saved contacts
                var savedContacts = this.getSavedContacts();
                
                // Add profile ID to saved list
                if (savedContacts.indexOf(profileId) === -1) {
                    savedContacts.push(profileId);
                    localStorage.setItem(this.storageKey, JSON.stringify(savedContacts));
                }
                
                // Save contact data
                var contactDataStorage = this.getContactData();
                contactDataStorage[profileId] = contactData;
                localStorage.setItem(this.contactDataKey, JSON.stringify(contactDataStorage));
                
                return true;
            } catch (e) {
                console.error('Error saving contact:', e);
                return false;
            }
        },

        /**
         * Remove contact from local storage
         */
        removeContact: function(profileId) {
            try {
                // Remove from saved contacts list
                var savedContacts = this.getSavedContacts();
                var index = savedContacts.indexOf(profileId);
                if (index > -1) {
                    savedContacts.splice(index, 1);
                    localStorage.setItem(this.storageKey, JSON.stringify(savedContacts));
                }
                
                // Remove contact data
                var contactDataStorage = this.getContactData();
                delete contactDataStorage[profileId];
                localStorage.setItem(this.contactDataKey, JSON.stringify(contactDataStorage));
                
                return true;
            } catch (e) {
                console.error('Error removing contact:', e);
                return false;
            }
        },

        /**
         * Check if contact is saved
         */
        isContactSaved: function(profileId) {
            var savedContacts = this.getSavedContacts();
            return savedContacts.indexOf(profileId) !== -1;
        },

        /**
         * Get saved contacts from local storage
         */
        getSavedContacts: function() {
            try {
                return JSON.parse(localStorage.getItem(this.storageKey) || '[]');
            } catch (e) {
                console.error('Error getting saved contacts:', e);
                return [];
            }
        },

        /**
         * Get contact data from local storage
         */
        getContactData: function() {
            try {
                return JSON.parse(localStorage.getItem(this.contactDataKey) || '{}');
            } catch (e) {
                console.error('Error getting contact data:', e);
                return {};
            }
        },

        /**
         * Get contact by ID
         */
        getContact: function(profileId) {
            var contactData = this.getContactData();
            return contactData[profileId] || null;
        },

        /**
         * Initialize contact list functionality
         */
        initContactList: function() {
            // Update save button states on page load
            this.updateSaveButtonStates();
            
            // Update contact count
            this.updateContactCount();
            
            // Load cloud contacts for logged-in users
            if (vcard_contact_manager && vcard_contact_manager.is_logged_in) {
                this.loadFromCloud();
            }
        },

        /**
         * Update save button states based on saved contacts
         */
        updateSaveButtonStates: function() {
            var self = this;
            $('.vcard-save-contact-btn').each(function() {
                var $button = $(this);
                var profileId = $button.data('profile-id');
                
                if (self.isContactSaved(profileId)) {
                    $button.html('<i class="fas fa-check"></i> Saved!')
                           .addClass('saved')
                           .removeClass('vcard-save-contact-btn')
                           .addClass('vcard-remove-contact-btn');
                }
            });
        },

        /**
         * Update contact count display
         */
        updateContactCount: function() {
            var count = this.getSavedContacts().length;
            $('.vcard-contact-count').text(count);
            
            // Show/hide contact count badge
            if (count > 0) {
                $('.vcard-contact-count').show();
            } else {
                $('.vcard-contact-count').hide();
            }
        },

        /**
         * Show contact list modal
         */
        showContactList: function(e) {
            if (e) e.preventDefault();
            
            // Create modal if it doesn't exist
            if (!$('.vcard-contact-list-modal').length) {
                this.createContactListModal();
            }
            
            // Render contact list
            this.renderContactList();
            
            // Show modal
            $('.vcard-contact-list-modal').fadeIn();
            $('body').addClass('vcard-modal-open');
        },

        /**
         * Hide contact list modal
         */
        hideContactList: function(e) {
            if (e && $(e.target).closest('.vcard-modal-content').length) {
                return;
            }
            
            $('.vcard-contact-list-modal').fadeOut();
            $('body').removeClass('vcard-modal-open');
        },

        /**
         * Create contact list modal
         */
        createContactListModal: function() {
            var modalHtml = `
                <div class="vcard-contact-list-modal">
                    <div class="vcard-modal-overlay"></div>
                    <div class="vcard-modal-content">
                        <div class="vcard-modal-header">
                            <h3><i class="fas fa-address-book"></i> Saved Contacts</h3>
                            <button class="vcard-modal-close"><i class="fas fa-times"></i></button>
                        </div>
                        <div class="vcard-modal-body">
                            <div class="vcard-contact-controls">
                                <div class="vcard-contact-search-wrapper">
                                    <input type="text" class="vcard-contact-search" placeholder="Search contacts...">
                                    <i class="fas fa-search"></i>
                                </div>
                                <select class="vcard-contact-filter">
                                    <option value="">All Templates</option>
                                </select>
                                <div class="vcard-contact-actions">
                                    <button class="vcard-export-contacts-btn"><i class="fas fa-download"></i> Export All</button>
                                    ${vcard_contact_manager && vcard_contact_manager.is_logged_in ? 
                                        '<button class="vcard-sync-cloud-btn"><i class="fas fa-sync-alt"></i> Sync Cloud</button>' : 
                                        '<button class="vcard-register-for-sync-btn"><i class="fas fa-user-plus"></i> Register to Sync</button>'
                                    }
                                    <button class="vcard-clear-contacts-btn"><i class="fas fa-trash"></i> Clear All</button>
                                </div>
                            </div>
                            <div class="vcard-contact-list"></div>
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(modalHtml);
        },

        /**
         * Render contact list
         */
        renderContactList: function() {
            var savedContacts = this.getSavedContacts();
            var contactData = this.getContactData();
            var $contactList = $('.vcard-contact-list');
            
            if (savedContacts.length === 0) {
                $contactList.html('<div class="vcard-no-contacts"><i class="fas fa-address-book"></i><p>No saved contacts yet</p></div>');
                return;
            }
            
            var contactsHtml = '';
            var templates = new Set();
            
            savedContacts.forEach(function(profileId) {
                var contact = contactData[profileId];
                if (!contact) return;
                
                templates.add(contact.template_name);
                
                var savedDate = new Date(contact.saved_at).toLocaleDateString();
                
                contactsHtml += `
                    <div class="vcard-contact-item" data-template="${contact.template_name}">
                        <div class="vcard-contact-avatar">
                            ${contact.logo_url ? 
                                `<img src="${contact.logo_url}" alt="${contact.business_name}">` : 
                                `<div class="vcard-contact-placeholder"><i class="fas fa-user"></i></div>`
                            }
                        </div>
                        <div class="vcard-contact-info">
                            <h4>${contact.business_name || contact.owner_name}</h4>
                            ${contact.job_title ? `<p class="vcard-contact-title">${contact.job_title}</p>` : ''}
                            ${contact.phone ? `<p class="vcard-contact-phone"><i class="fas fa-phone"></i> ${contact.phone}</p>` : ''}
                            ${contact.email ? `<p class="vcard-contact-email"><i class="fas fa-envelope"></i> ${contact.email}</p>` : ''}
                            <p class="vcard-contact-saved">Saved: ${savedDate}</p>
                        </div>
                        <div class="vcard-contact-actions">
                            <a href="${contact.profile_url}" class="vcard-contact-view" target="_blank" title="View Profile">
                                <i class="fas fa-eye"></i>
                            </a>
                            <button class="vcard-contact-export" data-profile-id="${profileId}" title="Export vCard">
                                <i class="fas fa-download"></i>
                            </button>
                            <button class="vcard-contact-remove" data-profile-id="${profileId}" title="Remove Contact">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                `;
            });
            
            $contactList.html(contactsHtml);
            
            // Update template filter options
            this.updateTemplateFilter(Array.from(templates));
            
            // Bind contact item events
            this.bindContactItemEvents();
        },

        /**
         * Update template filter options
         */
        updateTemplateFilter: function(templates) {
            var $filter = $('.vcard-contact-filter');
            var currentValue = $filter.val();
            
            $filter.find('option:not(:first)').remove();
            
            templates.forEach(function(template) {
                var templateName = template.charAt(0).toUpperCase() + template.slice(1);
                $filter.append(`<option value="${template}">${templateName}</option>`);
            });
            
            $filter.val(currentValue);
        },

        /**
         * Bind contact item events
         */
        bindContactItemEvents: function() {
            var self = this;
            
            // Export single contact
            $('.vcard-contact-export').off('click').on('click', function(e) {
                e.preventDefault();
                var profileId = $(this).data('profile-id');
                self.exportSingleContact(profileId);
            });
            
            // Remove single contact
            $('.vcard-contact-remove').off('click').on('click', function(e) {
                e.preventDefault();
                var profileId = $(this).data('profile-id');
                
                if (confirm('Are you sure you want to remove this contact?')) {
                    self.removeContact(profileId);
                    self.renderContactList();
                    self.updateContactCount();
                }
            });
        },

        /**
         * Handle contact search
         */
        handleContactSearch: function(e) {
            var searchTerm = $(e.target).val().toLowerCase();
            
            $('.vcard-contact-item').each(function() {
                var $item = $(this);
                var text = $item.text().toLowerCase();
                
                if (text.indexOf(searchTerm) !== -1) {
                    $item.show();
                } else {
                    $item.hide();
                }
            });
        },

        /**
         * Handle contact filter
         */
        handleContactFilter: function(e) {
            var filterValue = $(e.target).val();
            
            if (filterValue === '') {
                $('.vcard-contact-item').show();
            } else {
                $('.vcard-contact-item').each(function() {
                    var $item = $(this);
                    var template = $item.data('template');
                    
                    if (template === filterValue) {
                        $item.show();
                    } else {
                        $item.hide();
                    }
                });
            }
        },

        /**
         * Handle export all contacts
         */
        handleExportContacts: function(e) {
            e.preventDefault();
            
            var savedContacts = this.getSavedContacts();
            
            if (savedContacts.length === 0) {
                this.showMessage('No contacts to export', 'info');
                return;
            }
            
            // Show format selection
            this.showExportOptions();
        },

        /**
         * Show export options modal
         */
        showExportOptions: function() {
            var optionsHtml = `
                <div class="vcard-export-options-modal">
                    <div class="vcard-modal-overlay"></div>
                    <div class="vcard-modal-content">
                        <div class="vcard-modal-header">
                            <h3><i class="fas fa-download"></i> Export Contacts</h3>
                            <button class="vcard-modal-close"><i class="fas fa-times"></i></button>
                        </div>
                        <div class="vcard-modal-body">
                            <p>Choose export format:</p>
                            <div class="vcard-export-formats">
                                <button class="vcard-export-format-btn" data-format="vcf">
                                    <i class="fas fa-address-card"></i>
                                    <span>vCard (.vcf)</span>
                                    <small>Standard contact format</small>
                                </button>
                                <button class="vcard-export-format-btn" data-format="csv">
                                    <i class="fas fa-table"></i>
                                    <span>CSV (.csv)</span>
                                    <small>Spreadsheet format</small>
                                </button>
                                <button class="vcard-export-format-btn" data-format="json">
                                    <i class="fas fa-code"></i>
                                    <span>JSON (.json)</span>
                                    <small>Data format</small>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(optionsHtml);
            $('.vcard-export-options-modal').fadeIn();
            
            // Bind export format selection
            $('.vcard-export-format-btn').on('click', (e) => {
                var format = $(e.currentTarget).data('format');
                this.exportAllContacts(format);
                $('.vcard-export-options-modal').remove();
            });
            
            // Bind close button
            $('.vcard-export-options-modal .vcard-modal-close, .vcard-export-options-modal .vcard-modal-overlay').on('click', function() {
                $('.vcard-export-options-modal').remove();
            });
        },

        /**
         * Export all contacts in specified format
         */
        exportAllContacts: function(format) {
            var savedContacts = this.getSavedContacts();
            var contactData = this.getContactData();
            var contacts = [];
            
            savedContacts.forEach(function(profileId) {
                var contact = contactData[profileId];
                if (contact) {
                    contacts.push(contact);
                }
            });
            
            if (contacts.length === 0) {
                this.showMessage('No contacts to export', 'info');
                return;
            }
            
            var exportData, filename, mimeType;
            
            switch (format) {
                case 'vcf':
                    exportData = this.generateVCardFile(contacts);
                    filename = 'vcard_contacts_' + this.getDateString() + '.vcf';
                    mimeType = 'text/vcard';
                    break;
                    
                case 'csv':
                    exportData = this.generateCSVFile(contacts);
                    filename = 'vcard_contacts_' + this.getDateString() + '.csv';
                    mimeType = 'text/csv';
                    break;
                    
                case 'json':
                    exportData = JSON.stringify(contacts, null, 2);
                    filename = 'vcard_contacts_' + this.getDateString() + '.json';
                    mimeType = 'application/json';
                    break;
                    
                default:
                    this.showMessage('Invalid export format', 'error');
                    return;
            }
            
            this.downloadFile(exportData, filename, mimeType);
            this.showMessage(`Exported ${contacts.length} contacts as ${format.toUpperCase()}`, 'success');
        },

        /**
         * Export single contact
         */
        exportSingleContact: function(profileId) {
            var contact = this.getContact(profileId);
            
            if (!contact) {
                this.showMessage('Contact not found', 'error');
                return;
            }
            
            var exportData = this.generateVCardFile([contact]);
            var filename = this.sanitizeFilename(contact.business_name || contact.owner_name) + '.vcf';
            
            this.downloadFile(exportData, filename, 'text/vcard');
            this.showMessage('Contact exported successfully', 'success');
        },

        /**
         * Generate vCard file content
         */
        generateVCardFile: function(contacts) {
            var vcardContent = '';
            
            contacts.forEach(function(contact) {
                vcardContent += 'BEGIN:VCARD\n';
                vcardContent += 'VERSION:3.0\n';
                
                if (contact.business_name) {
                    vcardContent += 'FN:' + contact.business_name + '\n';
                    vcardContent += 'ORG:' + contact.business_name + '\n';
                }
                
                if (contact.owner_name) {
                    vcardContent += 'N:' + contact.owner_name + ';;;;\n';
                    if (!contact.business_name) {
                        vcardContent += 'FN:' + contact.owner_name + '\n';
                    }
                }
                
                if (contact.job_title) {
                    vcardContent += 'TITLE:' + contact.job_title + '\n';
                }
                
                if (contact.phone) {
                    vcardContent += 'TEL;TYPE=WORK,VOICE:' + contact.phone + '\n';
                }
                
                if (contact.email) {
                    vcardContent += 'EMAIL;TYPE=WORK:' + contact.email + '\n';
                }
                
                if (contact.website) {
                    vcardContent += 'URL:' + contact.website + '\n';
                }
                
                if (contact.address) {
                    vcardContent += 'ADR;TYPE=WORK:;;' + contact.address + ';;;;\n';
                }
                
                if (contact.business_description) {
                    vcardContent += 'NOTE:' + contact.business_description + '\n';
                }
                
                vcardContent += 'END:VCARD\n';
            });
            
            return vcardContent;
        },

        /**
         * Generate CSV file content
         */
        generateCSVFile: function(contacts) {
            var headers = [
                'Business Name', 'Owner Name', 'Job Title', 'Phone', 'Email', 
                'Website', 'Address', 'Description', 'Profile URL', 'Saved Date'
            ];
            
            var csvContent = headers.join(',') + '\n';
            
            contacts.forEach(function(contact) {
                var row = [
                    contact.business_name || '',
                    contact.owner_name || '',
                    contact.job_title || '',
                    contact.phone || '',
                    contact.email || '',
                    contact.website || '',
                    contact.address || '',
                    contact.business_description || '',
                    contact.profile_url || '',
                    contact.saved_at || ''
                ];
                
                // Escape commas and quotes in CSV
                row = row.map(function(field) {
                    if (typeof field === 'string' && (field.includes(',') || field.includes('"'))) {
                        return '"' + field.replace(/"/g, '""') + '"';
                    }
                    return field;
                });
                
                csvContent += row.join(',') + '\n';
            });
            
            return csvContent;
        },

        /**
         * Handle clear all contacts
         */
        handleClearContacts: function(e) {
            e.preventDefault();
            
            var savedContacts = this.getSavedContacts();
            
            if (savedContacts.length === 0) {
                this.showMessage('No contacts to clear', 'info');
                return;
            }
            
            if (confirm(`Are you sure you want to remove all ${savedContacts.length} saved contacts? This action cannot be undone.`)) {
                try {
                    localStorage.removeItem(this.storageKey);
                    localStorage.removeItem(this.contactDataKey);
                    
                    this.showMessage('All contacts cleared', 'success');
                    this.renderContactList();
                    this.updateContactCount();
                    this.updateSaveButtonStates();
                    
                } catch (e) {
                    console.error('Error clearing contacts:', e);
                    this.showMessage('Error clearing contacts', 'error');
                }
            }
        },

        /**
         * Handle cloud sync button
         */
        handleSyncCloud: function(e) {
            e.preventDefault();
            
            var $button = $(e.currentTarget);
            $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Syncing...');
            
            this.syncWithCloud();
            
            setTimeout(() => {
                $button.prop('disabled', false).html('<i class="fas fa-sync-alt"></i> Sync Cloud');
            }, 2000);
        },

        /**
         * Handle register for sync button
         */
        handleRegisterForSync: function(e) {
            e.preventDefault();
            
            // Trigger registration modal if available
            if (window.VCardUserRegistration) {
                VCardUserRegistration.showRegistrationModal(e);
            } else {
                this.showMessage('Registration system not available', 'error');
            }
        },

        /**
         * Download file
         */
        downloadFile: function(content, filename, mimeType) {
            var blob = new Blob([content], { type: mimeType });
            var url = window.URL.createObjectURL(blob);
            
            var a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            
            window.URL.revokeObjectURL(url);
        },

        /**
         * Get date string for filenames
         */
        getDateString: function() {
            var now = new Date();
            return now.getFullYear() + '-' + 
                   String(now.getMonth() + 1).padStart(2, '0') + '-' + 
                   String(now.getDate()).padStart(2, '0');
        },

        /**
         * Sanitize filename
         */
        sanitizeFilename: function(filename) {
            return filename.replace(/[^a-z0-9]/gi, '_').toLowerCase();
        },

        /**
         * Save contact to cloud (for logged-in users)
         */
        saveToCloud: function(profileId, contactData) {
            $.ajax({
                url: vcard_contact_manager.ajax_url,
                type: 'POST',
                data: {
                    action: 'vcard_save_contact_cloud',
                    profile_id: profileId,
                    contact_data: JSON.stringify(contactData),
                    nonce: vcard_contact_manager.nonce
                },
                success: function(response) {
                    if (response.success) {
                        console.log('Contact saved to cloud');
                    } else {
                        console.warn('Failed to save contact to cloud:', response.data);
                    }
                },
                error: function() {
                    console.warn('Network error saving contact to cloud');
                }
            });
        },

        /**
         * Sync local contacts with cloud
         */
        syncWithCloud: function() {
            if (!vcard_contact_manager || !vcard_contact_manager.is_logged_in) {
                this.showMessage(vcard_contact_manager.strings.login_required, 'error');
                return;
            }

            var localContacts = this.getContactData();
            
            if (Object.keys(localContacts).length === 0) {
                this.showMessage('No local contacts to sync', 'info');
                return;
            }

            $.ajax({
                url: vcard_contact_manager.ajax_url,
                type: 'POST',
                data: {
                    action: 'vcard_sync_contacts',
                    local_contacts: JSON.stringify(localContacts),
                    nonce: vcard_contact_manager.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showMessage(response.data.message, 'success');
                    } else {
                        this.showMessage(response.data || vcard_contact_manager.strings.cloud_sync_failed, 'error');
                    }
                },
                error: () => {
                    this.showMessage(vcard_contact_manager.strings.cloud_sync_failed, 'error');
                }
            });
        },

        /**
         * Load contacts from cloud
         */
        loadFromCloud: function() {
            if (!vcard_contact_manager || !vcard_contact_manager.is_logged_in) {
                return;
            }

            $.ajax({
                url: vcard_contact_manager.ajax_url,
                type: 'POST',
                data: {
                    action: 'vcard_get_saved_contacts',
                    nonce: vcard_contact_manager.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.mergeCloudContacts(response.data);
                    }
                },
                error: () => {
                    console.warn('Failed to load contacts from cloud');
                }
            });
        },

        /**
         * Merge cloud contacts with local storage
         */
        mergeCloudContacts: function(cloudContacts) {
            var localContacts = this.getContactData();
            var merged = false;

            Object.keys(cloudContacts).forEach((profileId) => {
                if (!localContacts[profileId]) {
                    localContacts[profileId] = cloudContacts[profileId];
                    merged = true;
                }
            });

            if (merged) {
                localStorage.setItem(this.contactDataKey, JSON.stringify(localContacts));
                
                // Update saved contacts list
                var savedContacts = this.getSavedContacts();
                Object.keys(cloudContacts).forEach((profileId) => {
                    if (savedContacts.indexOf(profileId) === -1) {
                        savedContacts.push(profileId);
                    }
                });
                localStorage.setItem(this.storageKey, JSON.stringify(savedContacts));
                
                this.updateContactCount();
                this.updateSaveButtonStates();
            }
        },

        /**
         * Show registration prompt for anonymous users
         */
        showRegistrationPrompt: function() {
            // Only show if user has saved multiple contacts
            var savedCount = this.getSavedContacts().length;
            
            if (savedCount >= 3) {
                setTimeout(() => {
                    var message = vcard_contact_manager.strings.register_prompt + 
                                ' <a href="#" class="vcard-register-btn" style="color: white; text-decoration: underline;">Create Account</a>';
                    
                    var $message = $('<div class="vcard-message vcard-message-info" style="cursor: pointer;">' + message + '</div>');
                    $('body').append($message);
                    
                    $message.fadeIn().delay(8000).fadeOut(function() {
                        $(this).remove();
                    });
                }, 2000);
            }
        },

        /**
         * Show message to user
         */
        showMessage: function(message, type) {
            type = type || 'info';
            
            var $message = $('<div class="vcard-message vcard-message-' + type + '">' + message + '</div>');
            $('body').append($message);
            
            $message.fadeIn().delay(3000).fadeOut(function() {
                $(this).remove();
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        VCardContactManager.init();
    });

    // Make globally available
    window.VCardContactManager = VCardContactManager;

})(jQuery);