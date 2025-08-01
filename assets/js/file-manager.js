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

// Toggle folder visibility
function toggleFolder(folderId) {
    const folderContent = document.getElementById(folderId);
    const toggleIcon = document.getElementById("toggle-" + folderId);
    
    if (folderContent.classList.contains("collapsed")) {
        folderContent.classList.remove("collapsed");
        toggleIcon.classList.remove("collapsed");
        toggleIcon.textContent = "▼";
    } else {
        folderContent.classList.add("collapsed");
        toggleIcon.classList.add("collapsed");
        toggleIcon.textContent = "▶";
    }
}

// Toggle all folders
function toggleAllFolders(expand) {
    const folderContents = document.querySelectorAll(".folder-content");
    const toggleIcons = document.querySelectorAll(".folder-toggle");
    
    folderContents.forEach(function(folder) {
        if (expand) {
            folder.classList.remove("collapsed");
        } else {
            folder.classList.add("collapsed");
        }
    });
    
    toggleIcons.forEach(function(icon) {
        if (expand) {
            icon.classList.remove("collapsed");
            icon.textContent = "▼";
        } else {
            icon.classList.add("collapsed");
            icon.textContent = "▶";
        }
    });
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
