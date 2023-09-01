<?php
/**
 * Appends or creates a BMP file from file chunks.
 *
 * @param string $chunkFile     The path to the current chunk file.
 * @param int    $chunkNumber   The current chunk number (0-based).
 * @param int    $totalChunks   The total number of chunks.
 * @param string $targetFile    The path to the target BMP file.
 *
 * @return bool True if the operation is successful for the last chunk, false otherwise.
 */
function save_chunks_to_bmp_file($chunkFile, $chunkNumber, $totalChunks, $targetFile)
{
    if ($chunkNumber == 0) {
        // Create a new BMP file if it's the first chunk
        $bmpData = file_get_contents($chunkFile);
        file_put_contents($targetFile, $bmpData);
    } else {
        if (file_exists($targetFile)) {
            // Append to existing BMP file for subsequent chunks
            $currentBmpData = file_get_contents($targetFile);
            $chunkData = file_get_contents($chunkFile);
            $bmpData = $currentBmpData . $chunkData;
            file_put_contents($targetFile, $bmpData);
        } else {
            setResponseCode(500, "File was unable to be created on the server.");
            exit(1);
        }
    }

    return ($chunkNumber == $totalChunks - 1);
}

/**
 * Generates the target file path for a BMP file in the specified directory.
 *
 * If the target directory does not exist, it will be created recursively.
 *
 * @param string $targetDirectory The directory where the BMP file should be stored.
 * @param string $fileName        The base name for the BMP file (without the extension).
 *
 * @return string The full path to the target BMP file, including the directory and extension.
 */
function get_taraget_file($targetDirectory, $fileName)
{
    // Check if the target directory exists, and if not, create it with full permissions (0777).
    if (!file_exists($targetDirectory)) {
        mkdir($targetDirectory, 0777, true);
    }

    // Generate the full path to the target BMP file by combining the directory and filename with '.bmp' extension.
    $targetFile = $targetDirectory . $fileName . '.bmp';

    return $targetFile;
}

/**
 * Validates a received file by comparing its SHA-256 hash with a given hash.
 *
 * This function calculates the SHA-256 hash of the received file and compares it
 * to the provided SHA-256 hash to determine if the file is valid and unchanged.
 *
 * @param string $receivedFile The path to the received file that needs validation.
 * @param string $sha256Hash   The expected SHA-256 hash value to compare against.
 *
 * @return bool True if the received file's hash matches the expected hash, indicating
 *              successful validation. False otherwise, indicating a potential issue
 *              with the uploaded file.
 */
function validate_received_file_by_sha256($receivedFile, $sha256Hash)
{
    $isValidationPassed = false;

    // Calculate the SHA-256 hash of the received file.
    $receivedFileHash = hash_file('sha256', $receivedFile);

    if ($receivedFileHash == $sha256Hash) {
        // Hashes match, indicating a successful validation.
        $isValidationPassed = true;
    } else {
        // Hashes do not match, indicating a potential issue with the uploaded file.
        setResponseCode(400, "Hashes do not match. There might be an issue with the uploaded file.");
        die();
    }

    return $isValidationPassed;
}


/**
 * Retrieves the RGB color values of a pixel from a BMP image file at the specified coordinates.
 *
 * This function reads the BMP file header and information header, validates the file format,
 * and then seeks to the given pixel coordinates to extract the RGB color values of the pixel.
 *
 * @param resource $file The file handle of the opened BMP image file.
 * @param int      $x    The x-coordinate (horizontal) of the pixel to retrieve.
 * @param int      $y    The y-coordinate (vertical) of the pixel to retrieve.
 *
 * @return array An associative array containing the RGB color values of the pixel:
 *               - "R" (Red) value
 *               - "G" (Green) value
 *               - "B" (Blue) value
 *
 * If the function encounters errors (e.g., invalid BMP format or coordinates), it sets an appropriate
 * HTTP response code using the setResponseCode function and terminates execution.
 */
function get_pixel_value_from_bmp_file($file, $x, $y)
{
    // Read BMP header and information header data.
    $header = unpack("vtype/Vsize/vreserved1/vreserved2/Voffset", fread($file, 14));
    $infoHeader = unpack("Vsize/lwidth/lheight/vplanes/vbitCount/Vcompression/VimageSize/lxPixelsPerMeter/lyPixelsPerMeter/VcolorsUsed/VcolorsImportant", fread($file, 40));

    // Check if the BMP file type is valid (0x4D42 indicates BMP format).
    if ($header['type'] != 0x4D42) {
        setResponseCode(400, "Not a BMP file!!");
        fclose($file);
        die();
    }

    $width = $infoHeader['width'];
    $height = $infoHeader['height'];


    // Validate the coordinates.
    if ($x < 0 || $x >= $width || $y < 0 || $y >= $height) {
        setResponseCode(500, "Invalid coordinates: " . $x . " " . $y);
        fclose($file);
        die();
    }

    // Seek to the pixel position in the BMP data.
    fseek($file, $header['offset'], SEEK_SET);

    // Calculate pixel-related values.
    $bytesPerPixel = $infoHeader['bitCount'] / 8;
    $padding = (4 - ($infoHeader['width'] * $bytesPerPixel) % 4) % 4;
    fseek($file, ($height - $y - 1) * ($width * $bytesPerPixel + $padding) + $x * $bytesPerPixel, SEEK_CUR);

    // Read and unpack RGB values of the pixel.
    $pixel = unpack("C3", fread($file, 3));

    $R = $pixel[3];
    $G = $pixel[2];
    $B = $pixel[1];

    $RGB = array(
        "R" => $R,
        "G" => $G,
        "B" => $B
    );

    // Return the file pointer to the beginning of the file.
    fseek($file, 0);

    return $RGB;

}


/**
 * Retrieves the width and height of a BMP image from its header.
 *
 * This function seeks to the appropriate position in the BMP image file where
 * the width and height information is stored, reads and converts the bytes to
 * integer values, and then returns an associative array containing the image width
 * and height.
 *
 * @param resource $imageFile The file handle of the opened BMP image file.
 *
 * @return array An associative array containing the image dimensions:
 *               - "width" (Width of the image)
 *               - "height" (Height of the image)
 */
function get_bmp_image_width_and_height($imageFile)
{
    // Move the file pointer to the position where width and height are stored in the BMP header (offset 18).
    fseek($imageFile, 18);

    // Read 4 bytes for width and 4 bytes for height.
    $widthBytes = fread($imageFile, 4);
    $heightBytes = fread($imageFile, 4);

    // Convert the bytes to integer values using Little-endian format (unpack 'V').
    $width = unpack('V', $widthBytes)[1];
    $height = unpack('V', $heightBytes)[1];

    // Return the file pointer to the beginning of the file.
    fseek($imageFile, 0);

    // Return an associative array containing the image width and height.
    return array(
        "width" => $width,
        "height" => $height
    );
}


/**
 * Retrieves the top N RGB color values and their corresponding percentages
 * from a BMP image file.
 *
 * This function opens the BMP image file, reads the pixel values, counts the
 * occurrences of each color, and then calculates and returns the top N colors
 * along with their percentage of occurrence in the image.
 *
 * @param string $filePath   The path to the BMP image file to analyze.
 * @param int    $numColors  The number of top colors to retrieve.
 *
 * @return array An associative array containing the top N colors and their percentages:
 *               - Each key represents an RGB color value in the format "R,G,B".
 *               - Each value represents the percentage of occurrence of that color in the image.
 *
 * If the function encounters errors (e.g., file opening error or corrupted image dimensions),
 * it sets an appropriate HTTP response code using the setResponseCode function and terminates execution.
 */
function get_top_pixel_rgb_from_file_path($filePath, $numColors)
{
    // Open the BMP image file for reading in binary mode.
    $file = fopen($filePath, "rb");

    // Check if the file was successfully opened.
    if (!$file) {
        echo "Error opening file\n";
        setResponseCode(500, "Error opening file");
        die();
    }

    // Retrieve the width and height of the image from its header.
    $imageWidthAndHeight = get_bmp_image_width_and_height($file);
    $width = $imageWidthAndHeight["width"];
    $height = $imageWidthAndHeight["height"];

    // Check for corrupted or invalid image dimensions.
    if ($width == 0 || $height == 0) {
        echo "Corrupted/Invalid image dimensions\n";
        setResponseCode(500, "Corrupted/Invalid image dimensions");
        die();
    }

    $colorCounts = [];

    // Loop through each pixel in the image.
    for ($x = 0; $x < $width; $x++) {
        for ($y = 0; $y < $height; $y++) {
            $pixel = get_pixel_value_from_bmp_file($file, $x, $y);
            $r = $pixel['R'];
            $g = $pixel['G'];
            $b = $pixel['B'];

            $color = "$r,$g,$b";

            // Count occurrences of each color.
            if (isset($colorCounts[$color])) {
                $colorCounts[$color]++;
            } else {
                $colorCounts[$color] = 1;
            }
        }
    }

    // Sort colors by occurrences in descending order.
    arsort($colorCounts);

    // Get the top N colors.
    $topColors = array_slice(array_keys($colorCounts), 0, $numColors);

    // Calculate the total pixel count.
    $totalPixels = $width * $height;

    // Calculate the percentage of occurrence of each top color.
    $topColorPercentages = array();
    foreach ($topColors as $color) {
        $count = $colorCounts[$color];
        $percentage = ($count / $totalPixels) * 100;
        $topColorPercentages[$color] = $percentage;
    }

    // Close the image file.
    fclose($file);

    return $topColorPercentages;
}


/**
 * Sets the response code and reason
 *
 * @param int    $code
 * @param string $reason
 */
function setResponseCode($code, $reason = null) {
    $code = intval($code);

    if (version_compare(phpversion(), '5.4', '>') && is_null($reason))
        http_response_code($code);
    else
        header(trim("HTTP/1.0 $code $reason"));

}

?>