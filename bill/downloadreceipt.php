<?php
// This file is included by addPay.php. The variable $receipt_content_to_pass is available here.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Downloading Receipt...</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <style>
        body {
            font-family: sans-serif;
            background-color: #f0f2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            text-align: center;
        }
        .container {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            max-width: 500px;
        }
        h1 {
            color: #333;
        }
        p {
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Downloading Receipt...</h1>
        <p>Please wait while your PDF receipt is generated and downloaded.</p>
        <p>If the download does not start automatically, please refresh the page.</p>
        <!-- The fetched receipt will be placed here temporarily to be captured -->
        <div id="receipt-container" style="display: none;"></div>
    </div>

    <script>
        window.onload = function() {
            const receiptContainer = document.getElementById('receipt-container');
            const receiptContentHtml = `<?php echo addslashes($receipt_content_to_pass); ?>`;

            console.log('Injecting receipt content directly into the DOM...');
            
            receiptContainer.innerHTML = receiptContentHtml;
            const receiptContent = receiptContainer.querySelector('.b-receipt-container');

            if (!receiptContent) {
                console.error('Receipt content not found in fetched HTML. Check the class name.');
                document.querySelector('.container h1').textContent = 'Download Failed!';
                document.querySelector('.container p').textContent = 'Could not process the receipt content. Please try again.';
                return;
            }
            
            // The content is already in the DOM, use a brief delay
            // to allow for rendering before capture.
            setTimeout(() => {
                console.log('Rendering receipt to canvas...');
                html2canvas(receiptContent, {
                    scale: 2 // Increase scale for higher quality image
                }).then(canvas => {
                    console.log('Canvas created, generating PDF...');
                    const { jsPDF } = window.jspdf;
                    const doc = new jsPDF('p', 'mm', 'a4');
                    const imgData = canvas.toDataURL('image/png');
                    const imgWidth = 210;
                    const pageHeight = 297;
                    const imgHeight = canvas.height * imgWidth / canvas.width;
                    let heightLeft = imgHeight;
                    let position = 0;

                    doc.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                    heightLeft -= pageHeight;

                    while (heightLeft > 0) {
                        position = heightLeft - imgHeight;
                        doc.addPage();
                        doc.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                        heightLeft -= pageHeight;
                    }

                    const filename = 'receipt-' + new Date().toISOString().slice(0, 10) + '.pdf';
                    doc.save(filename);
                    
                    document.querySelector('.container h1').textContent = 'Download Complete!';
                    document.querySelector('.container p').textContent = 'Your receipt has been downloaded. You may now close this tab.';
                }).catch(error => {
                    console.error('Error generating PDF:', error);
                    document.querySelector('.container h1').textContent = 'Download Failed!';
                    document.querySelector('.container p').textContent = 'An error occurred while generating the PDF. Please try again or contact support.';
                });
            }, 1000); // 1000ms delay
        };
    </script>
</body>
</html>
