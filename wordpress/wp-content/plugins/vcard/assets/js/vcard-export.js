/**
 * Enhanced vCard Export JavaScript
 * Supports vCard 4.0 with comprehensive business data
 * 
 * @package vCard
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // vCard Export object
    var VCardExport = {
        
        /**
         * Initialize export functionality
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Enhanced vCard download button
            $(document).on('click', '.vcard-download-btn', this.handleDownload);
            
            // Multiple format export buttons
            $(document).on('click', '.vcard-export-vcf', function(e) {
                e.preventDefault();
                VCardExport.exportFormat('vcf');
            });
            
            $(document).on('click', '.vcard-export-csv', function(e) {
                e.preventDefault();
                VCardExport.exportFormat('csv');
            });
            
            // Bulk export functionality
            $(document).on('click', '.vcard-bulk-export', this.handleBulkExport);
        },

        /**
         * Handle vCard download with enhanced business data
         */
        handleDownload: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var profileId = $button.data('profile-id') || VCardExport.getCurrentProfileId();
            var format = $button.data('format') || 'vcf';
            
            if (!profileId) {
                VCardExport.showError('Profile ID not found');
                return;
            }
            
            // Show loading state
            VCardExport.setButtonLoading($button, true);
            
            // Generate and download vCard
            VCardExport.generateAndDownload(profileId, format)
                .finally(function() {
                    VCardExport.setButtonLoading($button, false);
                });
        },

        /**
         * Export in specific format
         */
        exportFormat: function(format) {
            var profileId = this.getCurrentProfileId();
            
            if (!profileId) {
                this.showError('Profile ID not found');
                return;
            }
            
            this.generateAndDownload(profileId, format);
        },

        /**
         * Generate and download vCard with comprehensive business data
         */
        generateAndDownload: function(profileId, format) {
            format = format || 'vcf';
            
            return new Promise(function(resolve, reject) {
                // First, get profile data via AJAX
                $.ajax({
                    url: vcard_export.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'get_vcard_export_data',
                        profile_id: profileId,
                        format: format,
                        nonce: vcard_export.nonce
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            if (format === 'vcf') {
                                VCardExport.downloadVCF(response.data, profileId);
                            } else if (format === 'csv') {
                                VCardExport.downloadCSV(response.data, profileId);
                            }
                            
                            // Track download
                            VCardExport.trackDownload(profileId, format);
                            resolve(response.data);
                        } else {
                            VCardExport.showError(response.data || 'Export failed');
                            reject(response.data);
                        }
                    },
                    error: function(xhr, status, error) {
                        VCardExport.showError('Network error: ' + error);
                        reject(error);
                    }
                });
            });
        },

        /**
         * Download VCF format
         */
        downloadVCF: function(vcardData, profileId) {
            var filename = vcardData.filename || ('vcard_' + profileId + '.vcf');
            var content = vcardData.content || this.generateVCardContent(vcardData);
            
            this.downloadFile(content, filename, 'text/vcard');
        },

        /**
         * Download CSV format
         */
        downloadCSV: function(csvData, profileId) {
            var filename = csvData.filename || ('vcard_' + profileId + '.csv');
            var content = csvData.content || this.generateCSVContent(csvData.data);
            
            this.downloadFile(content, filename, 'text/csv');
        },

        /**
         * Generate vCard 4.0 content with business data (fallback)
         */
        generateVCardContent: function(data) {
            var vcard = [];
            
            // vCard header
            vcard.push('BEGIN:VCARD');
            vcard.push('VERSION:4.0');
            
            // Basic information
            if (data.is_business) {
                vcard.push('FN:' + this.escapeVCardValue(data.business_name || ''));
                vcard.push('ORG:' + this.escapeVCardValue(data.business_name || ''));
                
                if (data.first_name || data.last_name) {
                    vcard.push('N:' + this.escapeVCardValue(data.last_name || '') + ';' + 
                              this.escapeVCardValue(data.first_name || '') + ';;;');
                }
                
                if (data.job_title) {
                    vcard.push('TITLE:' + this.escapeVCardValue(data.job_title));
                }
                
                if (data.business_tagline) {
                    vcard.push('ROLE:' + this.escapeVCardValue(data.business_tagline));
                }
            } else {
                var fullName = (data.first_name + ' ' + data.last_name).trim();
                vcard.push('FN:' + this.escapeVCardValue(fullName));
                vcard.push('N:' + this.escapeVCardValue(data.last_name || '') + ';' + 
                          this.escapeVCardValue(data.first_name || '') + ';;;');
                
                if (data.company) {
                    vcard.push('ORG:' + this.escapeVCardValue(data.company));
                }
                
                if (data.job_title) {
                    vcard.push('TITLE:' + this.escapeVCardValue(data.job_title));
                }
            }
            
            // Contact information
            if (data.phone) {
                vcard.push('TEL;TYPE=work,voice:' + this.escapeVCardValue(data.phone));
            }
            
            if (data.secondary_phone) {
                vcard.push('TEL;TYPE=work,voice:' + this.escapeVCardValue(data.secondary_phone));
            }
            
            if (data.whatsapp) {
                vcard.push('TEL;TYPE=work,cell:' + this.escapeVCardValue(data.whatsapp));
                vcard.push('IMPP;TYPE=work:whatsapp:' + this.escapeVCardValue(data.whatsapp));
            }
            
            if (data.email) {
                vcard.push('EMAIL;TYPE=work:' + this.escapeVCardValue(data.email));
            }
            
            if (data.website) {
                vcard.push('URL:' + this.escapeVCardValue(data.website));
            }
            
            // Address
            if (data.address || data.city || data.state || data.zip_code || data.country) {
                var addressParts = [
                    '', // Post office box
                    '', // Extended address
                    data.address || '',
                    data.city || '',
                    data.state || '',
                    data.zip_code || '',
                    data.country || ''
                ];
                
                var escapedParts = addressParts.map(function(part) {
                    return VCardExport.escapeVCardValue(part);
                });
                
                vcard.push('ADR;TYPE=work:' + escapedParts.join(';'));
            }
            
            // Geographic coordinates
            if (data.latitude && data.longitude) {
                vcard.push('GEO:' + data.latitude + ',' + data.longitude);
            }
            
            // Business description
            if (data.business_description) {
                vcard.push('NOTE:' + this.escapeVCardValue(data.business_description));
            }
            
            // Social media
            if (data.social_media) {
                for (var platform in data.social_media) {
                    if (data.social_media[platform]) {
                        vcard.push('X-SOCIALPROFILE;TYPE=' + platform + ':' + 
                                  this.escapeVCardValue(data.social_media[platform]));
                    }
                }
            }
            
            // Services
            if (data.services && data.services.length > 0) {
                data.services.forEach(function(service) {
                    if (service.name) {
                        var serviceInfo = service.name;
                        if (service.price) {
                            serviceInfo += ' - ' + service.price;
                        }
                        if (service.description) {
                            serviceInfo += ': ' + service.description;
                        }
                        vcard.push('X-SERVICE:' + VCardExport.escapeVCardValue(serviceInfo));
                    }
                });
            }
            
            // Products
            if (data.products && data.products.length > 0) {
                data.products.forEach(function(product) {
                    if (product.name) {
                        var productInfo = product.name;
                        if (product.price) {
                            productInfo += ' - ' + product.price;
                        }
                        if (product.description) {
                            productInfo += ': ' + product.description;
                        }
                        vcard.push('X-PRODUCT:' + VCardExport.escapeVCardValue(productInfo));
                    }
                });
            }
            
            // Business hours
            if (data.business_hours) {
                for (var day in data.business_hours) {
                    var schedule = data.business_hours[day];
                    if (schedule.closed) {
                        vcard.push('X-BUSINESS-HOURS;DAY=' + day.toUpperCase() + ':CLOSED');
                    } else if (schedule.open && schedule.close) {
                        vcard.push('X-BUSINESS-HOURS;DAY=' + day.toUpperCase() + ':' + 
                                  schedule.open + '-' + schedule.close);
                    }
                }
            }
            
            // Photo/Logo
            if (data.photo_url) {
                vcard.push('PHOTO:' + this.escapeVCardValue(data.photo_url));
            }
            
            // Categories
            if (data.categories && data.categories.length > 0) {
                vcard.push('CATEGORIES:' + this.escapeVCardValue(data.categories.join(',')));
            }
            
            // Revision timestamp
            vcard.push('REV:' + new Date().toISOString().replace(/[-:]/g, '').split('.')[0] + 'Z');
            
            // Unique identifier
            if (data.uid) {
                vcard.push('UID:' + this.escapeVCardValue(data.uid));
            }
            
            // vCard footer
            vcard.push('END:VCARD');
            
            return vcard.join('\r\n');
        },

        /**
         * Generate CSV content
         */
        generateCSVContent: function(data) {
            if (!data || typeof data !== 'object') {
                return '';
            }
            
            var headers = Object.keys(data);
            var values = headers.map(function(header) {
                var value = data[header] || '';
                // Escape CSV values
                if (typeof value === 'string' && (value.includes(',') || value.includes('"') || value.includes('\n'))) {
                    value = '"' + value.replace(/"/g, '""') + '"';
                }
                return value;
            });
            
            return headers.join(',') + '\n' + values.join(',');
        },

        /**
         * Handle bulk export
         */
        handleBulkExport: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var profileIds = VCardExport.getSelectedProfileIds();
            var format = $button.data('format') || 'vcf';
            
            if (profileIds.length === 0) {
                VCardExport.showError('No profiles selected');
                return;
            }
            
            VCardExport.setButtonLoading($button, true);
            
            // Export multiple profiles
            VCardExport.bulkExport(profileIds, format)
                .finally(function() {
                    VCardExport.setButtonLoading($button, false);
                });
        },

        /**
         * Bulk export multiple profiles
         */
        bulkExport: function(profileIds, format) {
            return new Promise(function(resolve, reject) {
                $.ajax({
                    url: vcard_export.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'bulk_vcard_export',
                        profile_ids: profileIds,
                        format: format,
                        nonce: vcard_export.nonce
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            // Download as ZIP file
                            VCardExport.downloadFile(
                                response.data.content,
                                response.data.filename,
                                'application/zip'
                            );
                            resolve(response.data);
                        } else {
                            VCardExport.showError(response.data || 'Bulk export failed');
                            reject(response.data);
                        }
                    },
                    error: function(xhr, status, error) {
                        VCardExport.showError('Network error: ' + error);
                        reject(error);
                    }
                });
            });
        },

        /**
         * Download file
         */
        downloadFile: function(content, filename, mimeType) {
            var blob = new Blob([content], { type: mimeType });
            var url = window.URL.createObjectURL(blob);
            
            var link = document.createElement('a');
            link.href = url;
            link.download = filename;
            link.style.display = 'none';
            
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            // Clean up
            window.URL.revokeObjectURL(url);
        },

        /**
         * Escape vCard value according to RFC 6350
         */
        escapeVCardValue: function(value) {
            if (!value) {
                return '';
            }
            
            return String(value)
                .replace(/\\/g, '\\\\')
                .replace(/;/g, '\\;')
                .replace(/,/g, '\\,')
                .replace(/\n/g, '\\n')
                .replace(/\r/g, '\\n');
        },

        /**
         * Get current profile ID
         */
        getCurrentProfileId: function() {
            // Try multiple methods to get profile ID
            var profileId = $('body').data('profile-id') || 
                           $('.vcard-profile').data('profile-id') ||
                           $('.vcard-single').data('profile-id') ||
                           $('input[name="profile_id"]').val();
            
            // Try to extract from URL if still not found
            if (!profileId) {
                var matches = window.location.pathname.match(/\/vcard\/(\d+)/);
                if (matches) {
                    profileId = matches[1];
                }
            }
            
            return profileId;
        },

        /**
         * Get selected profile IDs for bulk export
         */
        getSelectedProfileIds: function() {
            var ids = [];
            $('.vcard-profile-checkbox:checked').each(function() {
                ids.push($(this).val());
            });
            return ids;
        },

        /**
         * Track download event
         */
        trackDownload: function(profileId, format) {
            $.ajax({
                url: vcard_export.ajax_url,
                type: 'POST',
                data: {
                    action: 'track_vcard_download',
                    profile_id: profileId,
                    format: format,
                    nonce: vcard_export.nonce
                }
            });
        },

        /**
         * Set button loading state
         */
        setButtonLoading: function($button, loading) {
            if (loading) {
                $button.prop('disabled', true)
                       .data('original-text', $button.text())
                       .html('<i class="fas fa-spinner fa-spin"></i> ' + 
                             (vcard_export.strings.downloading || 'Downloading...'));
            } else {
                $button.prop('disabled', false)
                       .text($button.data('original-text') || $button.text().replace(/.*\s/, ''));
            }
        },

        /**
         * Show error message
         */
        showError: function(message) {
            if (typeof VCardPublic !== 'undefined' && VCardPublic.showMessage) {
                VCardPublic.showMessage(message, 'error');
            } else {
                alert('Error: ' + message);
            }
        },

        /**
         * Show success message
         */
        showSuccess: function(message) {
            if (typeof VCardPublic !== 'undefined' && VCardPublic.showMessage) {
                VCardPublic.showMessage(message, 'success');
            }
        },

        /**
         * Validate vCard content
         */
        validateVCard: function(vcardContent) {
            var errors = [];
            var warnings = [];
            
            // Basic structure validation
            if (!vcardContent.match(/^BEGIN:VCARD/m)) {
                errors.push('Missing BEGIN:VCARD');
            }
            
            if (!vcardContent.match(/^END:VCARD/m)) {
                errors.push('Missing END:VCARD');
            }
            
            if (!vcardContent.match(/^VERSION:[34]\.0/m)) {
                errors.push('Missing or invalid VERSION');
            }
            
            if (!vcardContent.match(/^FN:/m)) {
                errors.push('Missing required FN (Formatted Name) field');
            }
            
            // Line length validation
            var lines = vcardContent.split('\n');
            lines.forEach(function(line, index) {
                if (line.length > 75) {
                    warnings.push('Line ' + (index + 1) + ' exceeds 75 characters');
                }
            });
            
            return {
                valid: errors.length === 0,
                errors: errors,
                warnings: warnings
            };
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        VCardExport.init();
    });

    // Make VCardExport globally available
    window.VCardExport = VCardExport;

})(jQuery);