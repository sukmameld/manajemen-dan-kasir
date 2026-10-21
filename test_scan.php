<!doctype html>
<html>
<head>
    <title>Test Scan</title>
    <script src="https://unpkg.com/html5-qrcode"></script>
</head>
<body>
    <h2>Test Scanner</h2>
    <div id="reader" style="width: 400px;"></div>
    <p id="result"></p>

    <script>
        console.log("Script loaded, Html5Qrcode =", typeof Html5Qrcode);
        
        function onScanSuccess(text) {
            console.log("SCAN:", text);
            document.getElementById("result").innerText = "Scanned: " + text;
        }

        const scanner = new Html5QrcodeScanner("reader", { fps: 10, qrbox: 200 });
        scanner.render(onScanSuccess, (err) => {});
    </script>
</body>
</html>