const photoInput = document.querySelector('#verification_photo');
const photoPreview = document.querySelector('#verification-photo-preview');
const photoHelp = document.querySelector('#verification-photo-help');

if (photoInput instanceof HTMLInputElement && photoPreview instanceof HTMLImageElement && photoHelp) {
    let previewUrl = null;

    photoInput.addEventListener('change', () => {
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
            previewUrl = null;
        }

        photoPreview.removeAttribute('src');
        photoPreview.classList.add('hidden');
        photoInput.setCustomValidity('');
        photoHelp.textContent = 'Attach a clear photo of your student ID for verification. JPEG or PNG, up to 5 MB.';

        const file = photoInput.files?.[0];
        if (!file) {
            return;
        }

        if (!['image/jpeg', 'image/png'].includes(file.type) || file.size > 5 * 1024 * 1024) {
            photoInput.setCustomValidity('Choose a JPEG or PNG photo that is 5 MB or smaller.');
            photoHelp.textContent = 'Choose a JPEG or PNG photo that is 5 MB or smaller.';
            return;
        }

        previewUrl = URL.createObjectURL(file);
        photoPreview.src = previewUrl;
        photoPreview.classList.remove('hidden');
    });
}
