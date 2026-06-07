/**
 * File Preview Logic
 * Handles rendering of file previews in a Bootstrap Modal
 */

function renderFilePreview(fileId, mimeType, fileName) {
    const fileUrl = 'serve_file.php?id=' + fileId;
    const modalBody = document.getElementById('previewModalBody');
    const modalTitle = document.getElementById('previewModalLabel');
    const downloadBtn = document.getElementById('downloadBtn');
    
    // Reset content
    modalBody.innerHTML = '<div class="spinner-border text-primary my-5" role="status"><span class="visually-hidden">Loading...</span></div>';
    modalTitle.textContent = fileName;
    downloadBtn.href = fileUrl;
    downloadBtn.download = fileName; // Hint for browser
    
    // Show modal
    const previewModal = new bootstrap.Modal(document.getElementById('previewModal'));
    previewModal.show();
    
    // Determine how to render based on mime type
    setTimeout(() => {
        if (mimeType.startsWith('image/')) {
            renderImage(fileUrl, modalBody);
        } else if (mimeType.startsWith('video/')) {
            renderVideo(fileUrl, modalBody);
        } else if (mimeType === 'application/pdf') {
            renderPdf(fileUrl, modalBody);
        } else if (
            mimeType === 'application/msword' || 
            mimeType === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ) {
            renderWord(fileUrl, modalBody);
        } else {
            renderFallback(mimeType, modalBody);
        }
    }, 300); // Small delay to let modal open animation start
}

function renderImage(url, container) {
    const img = document.createElement('img');
    img.src = url;
    img.className = 'img-fluid rounded';
    img.style.maxHeight = '70vh';
    img.alt = 'Image Preview';
    
    img.onload = () => {
        container.innerHTML = '';
        container.appendChild(img);
    };
    
    img.onerror = () => {
        renderFallback('image/error', container, 'Failed to load image.');
    };
}

function renderVideo(url, container) {
    container.innerHTML = `
        <video controls preload="metadata" style="max-width: 100%; max-height: 70vh;" class="rounded">
            <source src="${url}" type="video/mp4">
            Your browser does not support the video tag.
        </video>
    `;
}

function renderPdf(url, container) {
    if (typeof pdfjsLib === 'undefined') {
        renderFallback('application/pdf', container, 'PDF viewer not loaded. Please download to view.');
        return;
    }
    
    // Initial loading state inside the container
    container.innerHTML = `
        <div class="text-center py-5" id="pdf-loading-indicator">
            <div class="spinner-border text-danger mb-3" role="status"></div>
            <h5>Loading PDF...</h5>
        </div>
        <div id="pdf-render-container" style="max-height: 70vh; overflow-y: auto; background-color: #f8f9fa; display: none;"></div>
    `;
    
    const renderContainer = document.getElementById('pdf-render-container');
    const loadingIndicator = document.getElementById('pdf-loading-indicator');
    
    const loadingTask = pdfjsLib.getDocument({
        url: url,
        withCredentials: true
    });
    loadingTask.promise.then(async function(pdf) {
        loadingIndicator.style.display = 'none';
        renderContainer.style.display = 'block';
        
        // Render all pages
        const numPages = pdf.numPages;
        const scale = 1.5;
        
        for (let pageNum = 1; pageNum <= numPages; pageNum++) {
            const page = await pdf.getPage(pageNum);
            const viewport = page.getViewport({scale: scale});
            
            const canvas = document.createElement('canvas');
            canvas.className = 'border shadow-sm mb-3 mx-auto d-block';
            canvas.style.maxWidth = '100%';
            canvas.style.height = 'auto';
            canvas.height = viewport.height;
            canvas.width = viewport.width;
            
            renderContainer.appendChild(canvas);
            
            const ctx = canvas.getContext('2d');
            const renderContext = {
                canvasContext: ctx,
                viewport: viewport
            };
            
            await page.render(renderContext).promise;
        }
    }).catch(function(reason) {
        renderFallback('application/pdf', container, 'Error loading PDF: ' + reason);
    });
}

function renderWord(url, container) {
    // Note: MS Office viewer requires a public URL. Since this is likely on localhost (XAMPP), 
    // it will fail to preview. We'll show a fallback message indicating this.
    const isLocalhost = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
    
    if (isLocalhost) {
        renderFallback('application/msword', container, 'Word document preview is not available on localhost. Please download the file.');
    } else {
        // In production with public URL
        const fullUrl = encodeURIComponent(window.location.origin + window.location.pathname.replace(/\/[^\/]*$/, '/') + url);
        container.innerHTML = `
            <iframe src="https://view.officeapps.live.com/op/embed.aspx?src=${fullUrl}" 
                    width="100%" height="500px" frameborder="0">
                This is an embedded <a target="_blank" href="http://office.com">Microsoft Office</a> document, powered by <a target="_blank" href="http://office.com/webapps">Office Online</a>.
            </iframe>
        `;
    }
}

function renderFallback(mimeType, container, customMessage = '') {
    let iconClass = 'fa-file';
    if (mimeType.includes('image')) iconClass = 'fa-file-image';
    else if (mimeType.includes('video')) iconClass = 'fa-file-video';
    else if (mimeType.includes('pdf')) iconClass = 'fa-file-pdf';
    else if (mimeType.includes('word') || mimeType.includes('document')) iconClass = 'fa-file-word';
    
    const message = customMessage || 'Preview not available for this file type.';
    
    container.innerHTML = `
        <div class="py-5 text-muted">
            <i class="fas ${iconClass} fa-5x mb-3"></i>
            <h5>No Preview Available</h5>
            <p>${message}</p>
        </div>
    `;
}

// Clean up video when modal is closed to stop audio playback
document.addEventListener('DOMContentLoaded', () => {
    const modalEl = document.getElementById('previewModal');
    if (modalEl) {
        modalEl.addEventListener('hidden.bs.modal', function () {
            const modalBody = document.getElementById('previewModalBody');
            modalBody.innerHTML = '';
        });
    }
});
