// vCard JavaScript functionality
let vcardData = {};

function initVCard(data) {
    vcardData = data;
}

function downloadVCard() {
    // Generate vCard file
    let vcard = 'BEGIN:VCARD\n';
    vcard += 'VERSION:3.0\n';
    vcard += 'FN:' + vcardData.name + '\n';
    
    if (vcardData.title && vcardData.title.trim()) {
        vcard += 'TITLE:' + vcardData.title + '\n';
    }
    if (vcardData.company && vcardData.company.trim()) {
        vcard += 'ORG:' + vcardData.company + '\n';
    }
    if (vcardData.email && vcardData.email.trim()) {
        vcard += 'EMAIL:' + vcardData.email + '\n';
    }
    if (vcardData.phone && vcardData.phone.trim()) {
        vcard += 'TEL:' + vcardData.phone + '\n';
    }
    if (vcardData.address && vcardData.address.trim()) {
        vcard += 'ADR:;;' + vcardData.address + ';;;\n';
    }
    
    vcard += 'END:VCARD';

    const blob = new Blob([vcard], { type: 'text/vcard' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = vcardData.filename || 'contact.vcf';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

function shareVCard() {
    if (navigator.share) {
        navigator.share({
            title: vcardData.name,
            text: 'Check out this digital business card',
            url: window.location.href
        });
    } else {
        // Fallback - copy URL to clipboard
        navigator.clipboard.writeText(window.location.href).then(() => {
            alert('vCard URL copied to clipboard!');
        }).catch(() => {
            // Fallback for older browsers
            const textArea = document.createElement('textarea');
            textArea.value = window.location.href;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            alert('vCard URL copied to clipboard!');
        });
    }
}