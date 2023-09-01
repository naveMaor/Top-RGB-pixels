var loadingTimeout; // Store the timeout reference
document.addEventListener('DOMContentLoaded', () => {
    const imageInput = document.getElementById('imageInput');
    const uploadButton = document.getElementById('uploadButton');

    uploadButton.addEventListener('click', async () => {
        const selectedFile = imageInput.files[0];

        if (!selectedFile) {
            alert('Please select a BMP image.');
            return;
        }

        const sha256Hash = await calculateSHA256(selectedFile);
        await uploadChunks(selectedFile, sha256Hash);
    });

    imageInput.addEventListener('change', (event) => {
        const selectedFile = event.target.files[0];
        if (selectedFile && selectedFile.type !== 'image/bmp') {
            alert('Please select a .bmp file.');
            event.target.value = ''; // Clear the input field
        }
    });

});

async function calculateSHA256(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = function() {
            const arrayBuffer = reader.result;
            const cryptoSubtle = window.crypto.subtle;

            cryptoSubtle.digest('SHA-256', arrayBuffer).then(hashBuffer => {
                const hashArray = Array.from(new Uint8Array(hashBuffer));
                const hashHex = hashArray.map(byte => byte.toString(16).padStart(2, '0')).join('');
                resolve(hashHex);
            }).catch(error => {
                reject(error);
            });
        };
        reader.readAsArrayBuffer(file);
    });
}
async function uploadChunks(file, sha256Hash) {
    const CHUNK_SIZE = 1024 * 1024; // 1MB
    const totalChunks = Math.ceil(file.size / CHUNK_SIZE);
    let currentChunk = 0;
    showLoading();

    while (currentChunk < totalChunks) {
        const start = currentChunk * CHUNK_SIZE;
        const end = Math.min(start + CHUNK_SIZE, file.size);
        const chunk = file.slice(start, end);

        const formData = new FormData();
        formData.append('chunk', chunk);
        formData.append('totalChunks', totalChunks);
        formData.append('currentChunk', currentChunk);
        formData.append('sha256Hash', sha256Hash);

        const response = await fetch('upload.php', {
            method: 'POST',
            body: formData,
        });


        const responseStatus = response.status;
        console.log(responseStatus);


        if (responseStatus !== 200 && responseStatus !== 201) {
            hideLoading();
            const message = response.statusText;
            alert('Upload failed. \n' + message);
            return;
        }
        if (responseStatus === 201) {
            // Hide the loading indicator

            const jsonResponse = await response.json(); // Parse JSON response
            displayColors(jsonResponse);
            displayImage(file);
            hideLoading();
        }
        currentChunk++;
    }

}
function displayColors(rgbPercentageObject) {
    const explainText = document.getElementById('explainText');
    explainText.style.display = 'block';

    const container = document.getElementById('colorContainer'); // Change 'colorContainer' to your actual container ID
    container.innerHTML = ''; // Clear previous contents

    Object.entries(rgbPercentageObject).forEach(([rgb, percentage], index) => {
        const colorBoxContainer = document.createElement('div');
        colorBoxContainer.className = 'color-box-container'; // Add a class for styling

        const colorBox = document.createElement('div');
        colorBox.style.backgroundColor = `rgb(${rgb})`;
        colorBox.className = 'color-box'; // Add a class for styling

        const label = document.createElement('span');
        const [r, g, b] = rgb.split(','); // Split the RGB string into individual components
        const formattedPercentage = percentage.toFixed(3); // Format the percentage to 3 decimal places
        label.textContent = `Color ${index + 1} (R: ${r}, G: ${g}, B: ${b}) - ${formattedPercentage}%`;
        label.className = 'color-label'; // Add a class for styling

        colorBoxContainer.appendChild(colorBox);
        colorBoxContainer.appendChild(label);

        container.appendChild(colorBoxContainer);
    });
}

function displayImage(imageFile) {
    const container = document.getElementById('imageContainer'); // Change 'imageContainer' to your actual container ID
    container.innerHTML = ''; // Clear previous contents

    const imageUrl = URL.createObjectURL(imageFile);

    const image = document.createElement('img');
    image.id = 'Uploaded-image';
    image.src = imageUrl;

    container.appendChild(image);
}


function showLoading() {
    const existLoadingElement = document.getElementById('loading');
    if (existLoadingElement) {
        return;
    }

    const loadingElement = document.createElement('div');
    loadingElement.id = 'loading';
    loadingElement.classList.add('loading-container');
    loadingElement.innerHTML = `
        <div class="loading-spinner"></div>
        <p>Loading...</p>
    `;

    document.body.appendChild(loadingElement);

    const timeoutDuration = 30000; // 30000 milliseconds (30 seconds)

    // Create a timeout that removes the loading element and shows an alert
    loadingTimeout = setTimeout(() => {
        const loadingElementToRemove = document.getElementById('loading');
        if (loadingElementToRemove) {
            loadingElementToRemove.remove();
            alert('Loading timed out. Please try again.');
        }
    }, timeoutDuration);
}


function hideLoading() {
    clearTimeout(loadingTimeout);
    const loadingElement = document.getElementById('loading');
    if (loadingElement) {
        loadingElement.remove();
    }
}