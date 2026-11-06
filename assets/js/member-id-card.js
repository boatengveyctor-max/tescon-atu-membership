(() => {
    const printButton = document.querySelector('[data-print-card]');
    printButton?.addEventListener('click', () => window.print());

    const downloadButton = document.getElementById('btnDownloadPng');
    const status = document.getElementById('cardActionStatus');
    if (!(downloadButton instanceof HTMLButtonElement) || !(status instanceof HTMLElement)) {
        return;
    }

    downloadButton.addEventListener('click', async () => {
        downloadButton.disabled = true;
        status.textContent = 'Preparing your card…';

        try {
            const response = await fetch('id_card.php?format=svg', {
                credentials: 'same-origin',
                headers: { Accept: 'image/svg+xml' }
            });
            if (!response.ok) {
                throw new Error(`Card export failed with status ${response.status}.`);
            }

            const imageUrl = URL.createObjectURL(await response.blob());
            try {
                const image = new Image();
                await new Promise((resolve, reject) => {
                    image.onload = resolve;
                    image.onerror = () => reject(new Error('The card image could not be rendered.'));
                    image.src = imageUrl;
                });

                const canvas = document.getElementById('exportCanvas');
                if (!(canvas instanceof HTMLCanvasElement)) {
                    throw new Error('The card export canvas is unavailable.');
                }

                const scale = 2;
                canvas.width = 856 * scale;
                canvas.height = 540 * scale;
                const context = canvas.getContext('2d');
                if (!context) {
                    throw new Error('The browser could not prepare the card image.');
                }

                context.scale(scale, scale);
                context.drawImage(image, 0, 0, 856, 540);
                const png = await new Promise((resolve, reject) => {
                    canvas.toBlob((blob) => {
                        if (blob) {
                            resolve(blob);
                        } else {
                            reject(new Error('The browser could not encode the card image.'));
                        }
                    }, 'image/png');
                });

                const downloadUrl = URL.createObjectURL(png);
                const link = document.createElement('a');
                link.download = downloadButton.dataset.pngFilename || 'TESCON-ATU-ID.png';
                link.href = downloadUrl;
                link.click();
                window.setTimeout(() => URL.revokeObjectURL(downloadUrl), 1000);
                status.textContent = 'Your PNG card is ready.';
            } finally {
                URL.revokeObjectURL(imageUrl);
            }
        } catch (error) {
            console.error('Member ID card PNG export failed.', error);
            status.textContent = 'Card download failed. Please try again or use the SVG option.';
        } finally {
            downloadButton.disabled = false;
        }
    });
})();
