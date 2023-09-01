<?php
/**
 * This code snippet processes an uploaded file, which is sent in chunks, validates it using SHA-256 hash,
 * and extracts the top N RGB colors from the BMP image file.
 *
 * It follows these steps:
 * 1. Define the target directory, chunk information, SHA-256 hash, and chunk file.
 * 2. Generate the target BMP file path using the get_taraget_file function.
 * 3. Save the uploaded file chunks into the target BMP file using save_chunks_to_bmp_file.
 * 4. Validate the received file using SHA-256 hash with validate_received_file_by_sha256.
 * 5. If validation passes, extract the top N colors from the BMP image using get_top_pixel_rgb_from_file_path.
 * 6. Respond with an HTTP 201 status code and a JSON-encoded array of top colors if successful.
 * 7. If validation fails, respond with an HTTP 400 status code indicating a hash mismatch.
 */

require_once 'utility.php';
error_reporting(E_ERROR | E_PARSE);

$targetDirectory = 'uploads/';
$chunkNumber = $_POST['currentChunk'];
$totalChunks = $_POST['totalChunks'];
$sha256Hash = $_POST['sha256Hash'];
$numberOfColors = $_POST['numberOfColors'];
$chunkFile = $_FILES['chunk']['tmp_name'];



// Generate the target BMP file path
$targetFile = get_taraget_file($targetDirectory, 'uploaded_image');

// Save the uploaded file chunks into the target BMP file
$is_file_saved = save_chunks_to_bmp_file($chunkFile, $chunkNumber, $totalChunks, $targetFile);

if ($is_file_saved) {
    // Validate the received file using SHA-256 hash
    $isValidationPassed = validate_received_file_by_sha256($targetFile, $sha256Hash);

    if ($isValidationPassed) {
        // Extract the top N RGB colors from the BMP image
        $topColors = get_top_pixel_rgb_from_file_path($targetFile, $numberOfColors);

        // Respond with an HTTP 201 status code and JSON-encoded top colors
        http_response_code(201);
        header('Content-Type: application/json');
        echo json_encode($topColors);
    } else {
        // Respond with an HTTP 400 status code indicating a hash mismatch
        setResponseCode(400, "Hashes do not match. There might be an issue with the uploaded file.");
        die();
    }
}




?>