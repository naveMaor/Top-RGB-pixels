# Bitmap Image Analysis with PHP

Welcome to the Bitmap Image Analysis project! This repository aims to showcase a simple and efficient way to extract the top RGB pixels from a Bitmap image file without relying on external libraries. The server-side scripting language used in this project is PHP, and the analysis is performed based on the BMP file format.


# Introduction
This project demonstrates how to analyze BMP image files and extract the top RGB pixels without the need for any external libraries or dependencies.

# Getting Started
To get started with this project, follow these steps:

1. Clone the repository to your local machine:

```shell
git clone https://github.com/naveMaor/Top-RGB-pixels.git
```


2. Navigate to the project directory:
```shell
cd bitmap-image-analysis-php
```


3. Start a local PHP server (if not already running):
```shell
php -S localhost:8000
```

# Project Structure
The project structure is designed to be simple and easy to understand:

upload.php: This PHP script handles the uploading and processing of BMP image files. It interacts with utills.php to perform image analysis.
utills.php: A PHP utility script that contains functions and helper methods used for image analysis. It assists upload.php in processing BMP images effectively.
index.html: The main HTML file that provides the user interface for uploading BMP image files. Users can select and upload an image through this interface.
style.css: This CSS file contains styling rules to enhance the visual appearance of the index.html user interface. It helps make the web application more user-friendly.
script.js: A JavaScript file that adds interactivity to the web application. It may be used for client-side tasks such as validating user input and enhancing the user experience.

# Usage
To analyze a BMP image, simply upload the image file using the web interface provided at http://localhost:8000.

# Sample Output Screenshot


![image not found](https://i.ibb.co/qrLfmWn/1.png)
