// Upload form handler
document.getElementById("uploadForm").addEventListener("submit", async function(e) {
    e.preventDefault();
    
    const formData = new FormData();
    const fileInput = document.getElementById("fileInput");
    const pathInput = document.getElementById("pathInput");
    const messageDiv = document.getElementById("uploadMessage");
    
    if (!fileInput.files[0]) {
        showMessage("Please select a file to upload", "error");
        return;
    }
    
    formData.append("file", fileInput.files[0]);
    
    // Add path if specified
    const path = pathInput.value.trim();
    if (path) {
        formData.append("path", path);
    }
    
    try {
        showMessage("Uploading...", "info");
        
        const response = await fetch("/upload", {
            method: "POST",
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showMessage("File uploaded successfully!" + (path ? " to " + path : ""), "success");
            fileInput.value = "";
            pathInput.value = "";
            setTimeout(() => location.reload(), 1500);
        } else {
            showMessage("Upload failed: " + result.message, "error");
        }
    } catch (error) {
        showMessage("Upload failed: " + error.message, "error");
    }
});

// Download file
function downloadFile(filename) {
    window.open("/download?filename=" + encodeURIComponent(filename), "_blank");
}

// Delete file
async function deleteFile(filename) {
    if (!confirm("Are you sure you want to delete '" + filename + "'?")) {
        return;
    }
    
    try {
        const response = await fetch("/delete?filename=" + encodeURIComponent(filename), {
            method: "DELETE"
        });
        
        const result = await response.json();
        
        if (result.success) {
            showMessage("File deleted successfully!", "success");
            setTimeout(() => location.reload(), 1000);
        } else {
            showMessage("Delete failed: " + result.message, "error");
        }
    } catch (error) {
        showMessage("Delete failed: " + error.message, "error");
    }
}

// Show message
function showMessage(message, type) {
    const messageDiv = document.getElementById("uploadMessage");
    messageDiv.className = "message " + type;
    messageDiv.textContent = message;
    messageDiv.style.display = "block";
    
    if (type === "success" || type === "info") {
        setTimeout(() => {
            messageDiv.style.display = "none";
        }, 3000);
    }
}

// Toggle folder visibility and load contents if needed
async function toggleFolder(folderId, folderPrefix = null) {
    const folderContent = document.getElementById(folderId);
    const toggleIcon = document.getElementById("toggle-" + folderId);
    const folderSection = document.getElementById("section-" + folderId);
    
    if (folderContent.classList.contains("collapsed")) {
        // Expanding folder - load contents if not already loaded
        if (folderPrefix && folderContent.querySelector('.loading-indicator')) {
            await loadFolderContents(folderId, folderPrefix);
        }
        
        folderContent.classList.remove("collapsed");
        toggleIcon.classList.remove("collapsed");
        toggleIcon.textContent = "▼";
        
        // Show immediate child folders
        showChildFolders(folderId);
        
    } else {
        folderContent.classList.add("collapsed");
        toggleIcon.classList.add("collapsed");
        toggleIcon.textContent = "▶";
        
        // Hide all descendant folders
        hideDescendantFolders(folderId);
    }
}

// Show immediate child folders of the given folder
function showChildFolders(parentFolderId) {
    const childSections = document.querySelectorAll(`[data-parent="${parentFolderId}"]`);
    childSections.forEach(section => {
        section.style.display = 'block';
    });
}

// Hide all descendant folders (children, grandchildren, etc.)
function hideDescendantFolders(parentFolderId) {
    const childSections = document.querySelectorAll(`[data-parent="${parentFolderId}"]`);
    
    childSections.forEach(section => {
        // Hide the child section
        section.style.display = 'none';
        
        // Collapse the child folder if it's expanded
        const childFolderId = section.id.replace('section-', '');
        const childContent = document.getElementById(childFolderId);
        const childToggle = document.getElementById("toggle-" + childFolderId);
        
        if (childContent && !childContent.classList.contains("collapsed")) {
            childContent.classList.add("collapsed");
            if (childToggle) {
                childToggle.classList.add("collapsed");
                childToggle.textContent = "▶";
            }
        }
        
        // Recursively hide grandchildren
        hideDescendantFolders(childFolderId);
    });
}

// Load folder contents via AJAX
async function loadFolderContents(folderId, folderPrefix) {
    try {
        const response = await fetch("/list?prefix=" + encodeURIComponent(folderPrefix + "/"));
        const result = await response.json();
        
        const folderContent = document.getElementById(folderId);
        const countElement = document.getElementById("count-" + folderId);
        
        if (result.success && result.documents) {
            // Filter files to only include those directly in this folder (not in subfolders)
            const files = result.documents.filter(file => {
                // Skip the folder prefix itself
                if (file.name === folderPrefix + "/") {
                    return false;
                }
                
                // Must start with the folder prefix
                if (!file.name.startsWith(folderPrefix + "/")) {
                    return false;
                }
                
                // Get the relative path within the folder
                const relativePath = file.name.substring(folderPrefix.length + 1);
                
                // Only include files directly in this folder (no additional slashes = no subfolders)
                return !relativePath.includes('/');
                
            }).map(file => {
                // Create relative path within the folder
                const relativePath = file.name.substring(folderPrefix.length + 1);
                return {
                    ...file,
                    displayName: relativePath,
                    relativePath: relativePath
                };
            });
            
            // Update count
            if (countElement) {
                countElement.textContent = "(" + files.length + " files)";
            }
            
            // Generate HTML for files
            let filesHTML = '<div class="files-grid">';
            
            files.forEach(file => {
                const fileExt = getFileExtension(file.displayName);
                const isImage = isImageFile(file.displayName);
                const icon = isImage ? '' : getFileIcon(fileExt);
                const size = formatFileSize(file.size);
                const lastModified = formatDate(file.lastModified);
                
                filesHTML += `
                    <div class="file-card">
                        <div class="tooltip${isImage ? ' image-tooltip' : ''}">
                            <strong>${escapeHtml(file.name)}</strong><br>
                            Size: ${size}<br>
                            Type: ${escapeHtml(file.contentType)}<br>
                            Modified: ${lastModified}<br>
                            ETag: ${escapeHtml(file.etag)}
                            ${isImage ? `<br><img src="${escapeHtml(file.url)}" alt="Preview" />` : ''}
                        </div>`;
                
                if (isImage) {
                    filesHTML += `
                        <div class="file-icon image-preview">
                            <img src="${escapeHtml(file.url)}" alt="${escapeHtml(file.displayName)}" />
                        </div>`;
                } else {
                    filesHTML += `<div class="file-icon">${icon}</div>`;
                }
                
                filesHTML += `
                    <div class="file-name">${escapeHtml(file.displayName)}</div>
                    <div class="file-meta">${size} • ${lastModified}</div>`;
                
                if (isImage) {
                    filesHTML += `
                        <div class="image-controls">
                            <button class="image-control-btn" onclick="rotateImage('${escapeHtml(file.name)}', 90)" title="Rotate 90° clockwise">↻</button>
                            <button class="image-control-btn" onclick="rotateImage('${escapeHtml(file.name)}', -90)" title="Rotate 90° counter-clockwise">↺</button>
                            <button class="image-control-btn" onclick="flipImage('${escapeHtml(file.name)}', 'horizontal')" title="Flip horizontally">⇄</button>
                            <button class="image-control-btn" onclick="flipImage('${escapeHtml(file.name)}', 'vertical')" title="Flip vertically">⇅</button>
                        </div>`;
                }
                
                filesHTML += `
                    <div class="file-actions">
                        <button class="action-btn download-btn" onclick="downloadFile('${escapeHtml(file.name)}')">⬇️ Download</button>
                        <button class="action-btn delete-btn" onclick="deleteFile('${escapeHtml(file.name)}')">🗑️ Delete</button>
                    </div>
                </div>`;
            });
            
            filesHTML += '</div>';
            folderContent.innerHTML = filesHTML;
            
        } else {
            folderContent.innerHTML = '<div style="text-align: center; padding: 20px; color: #666;">No files found in this folder</div>';
            if (countElement) {
                countElement.textContent = "(0 files)";
            }
        }
        
    } catch (error) {
        console.error('Failed to load folder contents:', error);
        const folderContent = document.getElementById(folderId);
        folderContent.innerHTML = '<div style="text-align: center; padding: 20px; color: #d32f2f;">Failed to load folder contents</div>';
    }
}

// Helper functions for file processing
function getFileExtension(filename) {
    return filename.split('.').pop().toLowerCase();
}

function isImageFile(filename) {
    const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
    const ext = getFileExtension(filename);
    return imageExtensions.includes(ext);
}

function getFileIcon(extension) {
    const icons = {
        'pdf': '📄', 'doc': '📝', 'docx': '📝', 'xls': '📊', 'xlsx': '📊',
        'ppt': '📽️', 'pptx': '📽️', 'txt': '📄', 'jpg': '🖼️', 'jpeg': '🖼️',
        'png': '🖼️', 'gif': '🖼️', 'mp4': '🎬', 'avi': '🎬', 'mov': '🎬',
        'mp3': '🎵', 'wav': '🎵', 'zip': '📦', 'rar': '📦', 'html': '🌐',
        'css': '🎨', 'js': '⚡', 'php': '🐘', 'py': '🐍', 'json': '📋', 'xml': '📋'
    };
    return icons[extension] || '📄';
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Toggle all folders
function toggleAllFolders(expand) {
    const folderContents = document.querySelectorAll(".folder-content");
    const toggleIcons = document.querySelectorAll(".folder-toggle");
    const folderSections = document.querySelectorAll(".folder-section");
    
    if (expand) {
        // Show all folder sections and expand their content
        folderSections.forEach(function(section) {
            section.style.display = 'block';
        });
        
        folderContents.forEach(function(folder) {
            folder.classList.remove("collapsed");
        });
        
        toggleIcons.forEach(function(icon) {
            icon.classList.remove("collapsed");
            icon.textContent = "▼";
        });
    } else {
        // Collapse all folders and hide nested ones
        folderContents.forEach(function(folder) {
            folder.classList.add("collapsed");
        });
        
        toggleIcons.forEach(function(icon) {
            icon.classList.add("collapsed");
            icon.textContent = "▶";
        });
        
        // Hide all nested folders (level > 0)
        folderSections.forEach(function(section) {
            const level = section.getAttribute('data-level');
            if (level && parseInt(level) > 0) {
                section.style.display = 'none';
            }
        });
    }
    
    // Note: When expanding all, folders will load their contents when individually toggled
    // This prevents loading all folder contents at once which could be slow
}

// Image manipulation functions
function rotateImage(filename, degrees) {
    manipulateImage(filename, "rotate", degrees);
}

function flipImage(filename, direction) {
    manipulateImage(filename, "flip", direction);
}

async function manipulateImage(filename, operation, value) {
    try {
        // Find the file card and add processing class
        const fileCards = document.querySelectorAll(".file-card");
        let targetCard = null;
        
        fileCards.forEach(card => {
            const downloadBtn = card.querySelector(".download-btn");
            if (downloadBtn && downloadBtn.getAttribute("onclick").includes(filename)) {
                targetCard = card;
            }
        });
        
        if (targetCard) {
            targetCard.classList.add("processing");
        }
        
        showMessage("Processing image...", "info");
        
        // Download the image
        const response = await fetch("/download?filename=" + encodeURIComponent(filename));
        if (!response.ok) {
            throw new Error("Failed to download image");
        }
        
        const blob = await response.blob();
        
        // Create image element and canvas for manipulation
        const img = new Image();
        const canvas = document.createElement("canvas");
        const ctx = canvas.getContext("2d");
        
        img.onload = async function() {
            let width = img.width;
            let height = img.height;
            
            // Set canvas size based on operation
            if (operation === "rotate" && (Math.abs(value) === 90 || Math.abs(value) === 270)) {
                canvas.width = height;
                canvas.height = width;
            } else {
                canvas.width = width;
                canvas.height = height;
            }
            
            // Apply transformations
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            
            if (operation === "rotate") {
                ctx.translate(canvas.width / 2, canvas.height / 2);
                ctx.rotate((value * Math.PI) / 180);
                ctx.drawImage(img, -width / 2, -height / 2, width, height);
            } else if (operation === "flip") {
                if (value === "horizontal") {
                    ctx.scale(-1, 1);
                    ctx.drawImage(img, -width, 0, width, height);
                } else if (value === "vertical") {
                    ctx.scale(1, -1);
                    ctx.drawImage(img, 0, -height, width, height);
                }
            }
            
            // Convert canvas to blob
            canvas.toBlob(async function(newBlob) {
                try {
                    // Upload the modified image
                    const formData = new FormData();
                    
                    // Extract path and filename
                    const pathParts = filename.split("/");
                    const fileName = pathParts.pop();
                    const path = pathParts.join("/");
                    
                    // Create a file object with the original filename
                    const file = new File([newBlob], fileName, { type: blob.type });
                    
                    formData.append("file", file);
                    if (path) {
                        formData.append("path", path);
                    }
                    
                    const uploadResponse = await fetch("/upload", {
                        method: "POST",
                        body: formData
                    });
                    
                    const result = await uploadResponse.json();
                    
                    if (result.success) {
                        const operationText = operation === "rotate" ? 
                            (value > 0 ? "rotated clockwise" : "rotated counter-clockwise") :
                            "flipped " + value;
                        showMessage("Image " + operationText + " successfully!", "success");
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showMessage("Failed to upload modified image: " + result.message, "error");
                        if (targetCard) {
                            targetCard.classList.remove("processing");
                        }
                    }
                } catch (error) {
                    showMessage("Failed to upload modified image: " + error.message, "error");
                    if (targetCard) {
                        targetCard.classList.remove("processing");
                    }
                }
            }, blob.type || "image/jpeg", 0.95);
        };
        
        img.onerror = function() {
            showMessage("Failed to load image for processing", "error");
            if (targetCard) {
                targetCard.classList.remove("processing");
            }
        };
        
        // Load the image
        const imageUrl = URL.createObjectURL(blob);
        img.src = imageUrl;
        
    } catch (error) {
        showMessage("Image manipulation failed: " + error.message, "error");
        // Remove processing class from all cards in case of error
        document.querySelectorAll(".file-card.processing").forEach(card => {
            card.classList.remove("processing");
        });
    }
}

// Initialize folder states (expand all by default)
document.addEventListener("DOMContentLoaded", function() {
    // You can add code here to remember folder states or set default collapsed folders
});
