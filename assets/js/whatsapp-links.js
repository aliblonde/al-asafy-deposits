document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('footer a[href^="tel:"]').forEach(link => {
        const phone = link.getAttribute('href').replace(/[^0-9]/g, '');
        if (!phone) return;
        const whatsapp = document.createElement('a');
        whatsapp.href = 'https://wa.me/' + phone;
        whatsapp.target = '_blank';
        whatsapp.rel = 'noopener noreferrer';
        whatsapp.className = 'btn btn-sm btn-success ms-2';
        whatsapp.textContent = 'WhatsApp';
        whatsapp.setAttribute('aria-label', 'Contact ' + link.textContent.trim() + ' on WhatsApp');
        link.insertAdjacentElement('afterend', whatsapp);
    });
});
