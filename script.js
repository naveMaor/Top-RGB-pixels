var loadingTimeout; // Store the timeout reference
const CHUNK_SIZE = 1024 * 1024; // 1MB



document.addEventListener('DOMContentLoaded', () => {
    const imageInput = document.getElementById('imageInput');
    const uploadButton = document.getElementById('uploadButton');

    uploadButton.addEventListener('click', async () => {
        const numberOfColors = document.getElementById('numberOfColors').value;
        if(!validateNumerable(numberOfColors)){
            return;
        }


        const selectedFile = imageInput.files[0];

        if (!selectedFile) {
            alert('Please select a BMP image.');
            return;
        }

        const sha256Hash = await calculateSHA256(selectedFile);
        await uploadChunks(selectedFile, sha256Hash, numberOfColors);
    });

    imageInput.addEventListener('change', (event) => {
        const selectedFile = event.target.files[0];
        if (selectedFile && selectedFile.type !== 'image/bmp') {
            alert('Please select a .bmp file.');
            event.target.value = ''; // Clear the input field
        }
    });

});

/**
 * Calculate the SHA-256 hash of a given file using the Web Cryptography API.
 *
 * @param {File} file - The file for which to calculate the SHA-256 hash.
 * @returns {Promise<string>} A Promise that resolves with the hexadecimal representation
 *                            of the SHA-256 hash or rejects with an error if there's an issue.
 */
async function calculateSHA256(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();

        reader.onload = function() {
            const arrayBuffer = reader.result;

            // Use the Web Crypto API to calculate the SHA-256 hash of the file's content.
            const cryptoSubtle = window.crypto.subtle;
            cryptoSubtle.digest('SHA-256', arrayBuffer).then(hashBuffer => {
                // Convert the hash from an ArrayBuffer to a hexadecimal string.
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


/**
 * Upload a file in smaller chunks to a server using the Fetch API and display results.
 *
 * @param {File} file - The file to be uploaded.
 * @param {string} sha256Hash - The SHA-256 hash of the file for integrity verification.
 * @param {number} numberOfColors - The number of colors to be extracted from the image.
 */
async function uploadChunks(file, sha256Hash, numberOfColors) {

    // Calculate the total number of chunks required for the file.
    const totalChunks = Math.ceil(file.size / CHUNK_SIZE);

    // Initialize the current chunk counter.
    let currentChunk = 0;
    showLoading();

    // Iterate through each chunk of the file.
    while (currentChunk < totalChunks) {
        // Calculate the start and end positions for the current chunk.
        const start = currentChunk * CHUNK_SIZE;
        const end = Math.min(start + CHUNK_SIZE, file.size);

        // Slice the file to get the current chunk.
        const chunk = file.slice(start, end);

        // Create a FormData object to send the chunk and relevant metadata to the server.
        const formData = new FormData();
        formData.append('chunk', chunk);
        formData.append('totalChunks', totalChunks);
        formData.append('currentChunk', currentChunk);
        formData.append('sha256Hash', sha256Hash);
        formData.append('numberOfColors', numberOfColors);

        // Send the chunk to the server using a POST request.
        const response = await fetch('upload.php', {
            method: 'POST',
            body: formData,
        });

        // Get the HTTP response status code.
        const responseStatus = response.status;

        // Handle different response status codes.
        if (responseStatus !== 200 && responseStatus !== 201) {
            hideLoading();
            const message = response.statusText;
            alert('Upload failed. \n' + message);
            return;
        }

        if (responseStatus === 201) {
            const jsonResponse = await response.json();
            displayColors(jsonResponse);
            displayImage(file);
            hideLoading();
        }

        // Move to the next chunk.
        currentChunk++;
    }
}


/**
 * Display a set of colors with their RGB values and percentages in a web page.
 *
 * @param {Object} rgbPercentageObject - An object containing RGB color strings as keys
 *                                      and their corresponding percentage values as values.
 *                                      Example: { '255,0,0': 25.5, '0,128,255': 10.2, ... }
 */
function displayColors(rgbPercentageObject) {
    // Make the explanation text element visible by changing its display style.
    const explainText = document.getElementById('explainText');
    explainText.style.display = 'block';

    // Clear any previous contents in the container to start with a clean slate.
    const container = document.getElementById('colorContainer');
    container.innerHTML = '';

    // Iterate through the provided RGB color percentage object.
    Object.entries(rgbPercentageObject).forEach(([rgb, percentage], index) => {
        // Create a container div for each color box and its label.
        const colorBoxContainer = document.createElement('div');
        colorBoxContainer.className = 'color-box-container'; // Add a class for styling

        // Create a div element representing the color box and set its background color.
        const colorBox = document.createElement('div');
        colorBox.style.backgroundColor = `rgb(${rgb})`;
        colorBox.className = 'color-box';

        // Extract the R, G, and B components from the RGB string.
        const [r, g, b] = rgb.split(',');

        // Format the percentage to display with 3 decimal places.
        const formattedPercentage = percentage.toFixed(3);

        // Create a label for the color box with RGB values and percentage.
        const label = document.createElement('span');
        label.textContent = `Color ${index + 1} (R: ${r}, G: ${g}, B: ${b}) - ${formattedPercentage}%`;
        label.className = 'color-label';

        // Append the color box and label to the container div.
        colorBoxContainer.appendChild(colorBox);
        colorBoxContainer.appendChild(label);

        // Append the container div to the main color container in the HTML.
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


function validateNumerable(inputValue) {
    let isValid = false;
    // Check if the input value is a valid number
    if (!isNaN(inputValue)) {
        isValid = true;
    } else {
        alert("Invalid number of colors: " + inputValue);
    }
    return isValid;
}