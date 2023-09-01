<?php
error_reporting(E_ERROR | E_PARSE);


$targetDirectory = 'uploads/';
$chunkNumber = $_POST['currentChunk'];
$totalChunks = $_POST['totalChunks'];
$sha256Hash = $_POST['sha256Hash'];
$chunkFile = $_FILES['chunk']['tmp_name'];



$targetFile = get_taraget_file($targetDirectory, 'uploaded_image');
$is_file_saved = save_chunks_to_bmp_file($chunkFile, $chunkNumber, $totalChunks ,$targetFile);

if ($is_file_saved) {
    $isValidationPassed = validate_received_file_by_sha256($targetFile, $sha256Hash);
    if ($isValidationPassed) {
        $topColors = get_top_pixel_rgb_from_file_path($targetFile, 5);
        http_response_code(201);
        header('Content-Type: application/json');
        echo json_encode($topColors);
    }else{
        setResponseCode(400, "Hashes do not match. There might be an issue with the uploaded file.");
    }
}
else{
    //response to client 200
    setResponseCode(200);
}

function save_chunks_to_bmp_file($chunkFile, $chunkNumber, $totalChunks ,$targetFile)
{
    if ($chunkNumber == 0) {
        // Create a new file if it's the first chunk
        $outputFile = fopen($targetFile, 'wb');
    } else {
        $outputFile = fopen($targetFile, 'ab'); // Append to existing file for subsequent chunks
    }

    $chunkData = file_get_contents($chunkFile);
    fwrite($outputFile, $chunkData);
    fclose($outputFile);

    return ($chunkNumber == $totalChunks - 1);
}

function get_taraget_file($targetDirectory, $fileName)
{
    if (!file_exists($targetDirectory)) {
        mkdir($targetDirectory, 0777, true);
    }
    $targetFile = $targetDirectory . $fileName . '.jpeg';
    return $targetFile;
}

function validate_received_file_by_sha256($receivedFile, $sha256Hash)
{
    $isValidationPassed = false;
    $receivedFileHash = hash_file('sha256', $receivedFile);
    if ($receivedFileHash == $sha256Hash) {
        $isValidationPassed = true;
    } else {
        echo "\nHashes do not match. There might be an issue with the uploaded file.\n";
    }

    return $isValidationPassed;
}

function get_pixel_value_from_bmp_file($file, $x, $y)
{
    $header = unpack("vtype/Vsize/vreserved1/vreserved2/Voffset", fread($file, 14));
    $infoHeader = unpack("Vsize/lwidth/lheight/vplanes/vbitCount/Vcompression/VimageSize/lxPixelsPerMeter/lyPixelsPerMeter/VcolorsUsed/VcolorsImportant", fread($file, 40));

    if ($header['type'] != 0x4D42) {
        setResponseCode(500, "Not a BMP file!!");
        fclose($file);
        exit(1);
    }

    if ($x < 0 || $x >= $infoHeader['width'] || $y < 0 || $y >= $infoHeader['height']) {
        setResponseCode(500, "Invalid coordinates: ".$x." ".$y);
        fclose($file);
        exit(1);
    }

    fseek($file, $header['offset'], SEEK_SET);

    $bytesPerPixel = $infoHeader['bitCount'] / 8;
    $padding = (4 - ($infoHeader['width'] * $bytesPerPixel) % 4) % 4;
    fseek($file, ($infoHeader['height'] - $y - 1) * ($infoHeader['width'] * $bytesPerPixel + $padding) + $x * $bytesPerPixel, SEEK_CUR);

    $pixel = unpack("C3", fread($file, 3));

    $R = $pixel[3];
    $G = $pixel[2];
    $B = $pixel[1];

    $RGB = array(
        "R" => $R,
        "G" => $G,
        "B" => $B
    );

    //return the file pointer to the beginning of the file
    fseek($file, 0);

    return $RGB;
}

function get_bmp_image_width_and_height($imageFile){
    fseek($imageFile, 18); // Move to the position where width and height are stored
    $widthBytes = fread($imageFile, 4);
    $heightBytes = fread($imageFile, 4);

    // Convert bytes to integer (Little-endian format)
    $width = unpack('V', $widthBytes)[1];
    $height = unpack('V', $heightBytes)[1];

    fseek($imageFile, 0);


    return array(
        "width" => $width,
        "height" => $height
    );
}

function get_top_pixel_rgb_from_file_path($filePath, $numColors) {
    $file = fopen($filePath, "rb");
    if (!$file) {
        echo "Error opening file\n";
        setResponseCode(500, "Error opening file");
        exit(1);
    }

    $imageWidthAndHeight = get_bmp_image_width_and_height($file);
    $width = $imageWidthAndHeight["width"];
    $height = $imageWidthAndHeight["height"];
    if ($width == 0 || $height == 0) {
        echo "Corrupted/Invalid image dimensions\n";
        setResponseCode(500, "Corrupted/Invalid image dimensions");
        exit(1);
    }

    $colorCounts = [];

    // Loop through each pixel in the image
    for ($x = 0; $x < $width; $x++) {
        for ($y = 0; $y < $height; $y++) {
            $pixel = get_pixel_value_from_bmp_file($file, $x, $y);
            $r = $pixel['R'];
            $g = $pixel['G'];
            $b = $pixel['B'];

            $color = "$r,$g,$b";

            // Count occurrences of each color
            if (isset($colorCounts[$color])) {
                $colorCounts[$color]++;
            } else {
                $colorCounts[$color] = 1;
            }
        }
    }

    // Sort colors by occurrences in descending order
    arsort($colorCounts);

    // Get the top N colors
    $topColors = array_slice(array_keys($colorCounts), 0, $numColors);

    // Calculate total pixel count
    $totalPixels = $width * $height;

    $topColorPercentages = array();

    foreach ($topColors as $color) {
        $count = $colorCounts[$color]; // Assuming $colorCounts is defined
        $percentage = ($count / $totalPixels) * 100;
        $topColorPercentages[$color] = $percentage;
    }
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