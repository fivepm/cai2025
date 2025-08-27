<?php
// Tampilkan semua error PHP untuk debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'config/config.php';

// Bagian ini hanya akan berjalan jika ada permintaan POST dari JavaScript
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    $barcode = $data['barcode'] ?? '';

    $response = ['status' => 'error', 'message' => 'Data tidak valid.'];

    if (!empty($barcode)) {
        // $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
        if ($conn->connect_error) {
            $response['message'] = 'Koneksi database gagal: ' . $conn->connect_error;
        } else {
            $stmt = $conn->prepare("SELECT nama, kelompok FROM peserta WHERE barcode = ?");
            $stmt->bind_param("s", $barcode);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $peserta = $result->fetch_assoc();
                $response = ['status' => 'success', 'nama' => $peserta['nama'], 'kelompok' => $peserta['kelompok']];
            } else {
                $response['message'] = 'QR Code tidak terdaftar di database peserta hadir.';
            }
            $stmt->close();
            $conn->close();
        }
    }

    echo json_encode($response);
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cek QR Code</title>
    <link rel="icon" type="image/png" href="uploads/Logo 1x1.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
</head>

<body class="bg-gray-200">

    <div class="container mx-auto max-w-lg p-4">
        <img src="uploads/Logo 1x1.png" alt="Logo Acara" class="mx-auto h-20 w-auto">
        <h1 class="text-3xl font-bold text-center text-gray-800 my-4">Scanner Kehadiran Peserta</h1>

        <div id="reader" class="w-full rounded-lg overflow-hidden shadow-lg"></div>

        <div id="start-button-container" class="text-center mt-4">
            <button id="start-scan-button" class="px-6 py-3 bg-red-600 text-white font-bold rounded-lg shadow-md hover:bg-red-700">
                <i class="fas fa-camera mr-2"></i>Mulai Pindai
            </button>
        </div>

        <div id="result-container" class="mt-6">
            <!-- Hasil pindaian akan ditampilkan di sini -->
        </div>

        <div id="rescan-button-container" class="text-center mt-4" style="display: none;">
            <button id="rescan-button" class="px-6 py-3 bg-blue-600 text-white font-bold rounded-lg shadow-md hover:bg-blue-700">
                <i class="fas fa-sync-alt mr-2"></i>Scan Ulang
            </button>
        </div>
    </div>

    <script>
        const resultContainer = document.getElementById('result-container');
        const startButton = document.getElementById('start-scan-button');
        const startButtonContainer = document.getElementById('start-button-container');
        const rescanButton = document.getElementById('rescan-button');
        const rescanButtonContainer = document.getElementById('rescan-button-container');
        const html5QrcodeScanner = new Html5Qrcode("reader");

        function onScanSuccess(decodedText, decodedResult) {
            html5QrcodeScanner.pause();
            resultContainer.innerHTML = `<div class="p-4 bg-gray-100 rounded-lg text-center font-semibold animate-pulse">Memeriksa data...</div>`;

            fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        barcode: decodedText
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        // Jika server merespons dengan error (misal: 500), coba baca teks errornya
                        return response.text().then(text => {
                            throw new Error(text || `HTTP error! status: ${response.status}`)
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    let resultHtml = '';
                    if (data.status === 'success') {
                        resultHtml = `
                        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg shadow-md">
                            <div class="flex items-center">
                                <div class="text-3xl mr-4"><i class="fas fa-check-circle"></i></div>
                                <div>
                                    <p class="font-bold text-xl">${data.nama}</p>
                                    <p class="text-md">${data.kelompok}</p>
                                </div>
                            </div>
                        </div>`;
                    } else {
                        resultHtml = `
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg shadow-md">
                            <div class="flex items-center">
                                <div class="text-3xl mr-4"><i class="fas fa-times-circle"></i></div>
                                <div>
                                    <p class="font-bold text-xl">QR Code Tidak Ditemukan</p>
                                    <p class="text-md">${data.message}</p>
                                </div>
                            </div>
                        </div>`;
                    }
                    resultContainer.innerHTML = resultHtml;
                    rescanButtonContainer.style.display = 'block';
                })
                .catch(error => {
                    console.error('Error:', error);
                    // --- PERUBAHAN DI SINI: Tampilkan pesan error yang sebenarnya ---
                    resultContainer.innerHTML = `
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg shadow-md">
                        <p class="font-bold">Terjadi Kesalahan Server</p>
                        <p class="text-xs mt-2 bg-red-200 p-2 rounded"><code class="break-all">${error.message}</code></p>
                    </div>`;
                    rescanButtonContainer.style.display = 'block';
                });
        }

        startButton.addEventListener('click', () => {
            startButtonContainer.style.display = 'none';
            resultContainer.innerHTML = '';
            const config = {
                fps: 10,
                qrbox: {
                    width: 250,
                    height: 250
                }
            };
            html5QrcodeScanner.start({
                facingMode: "environment"
            }, config, onScanSuccess);
        });

        rescanButton.addEventListener('click', () => {
            resultContainer.innerHTML = '';
            rescanButtonContainer.style.display = 'none';
            html5QrcodeScanner.resume();
        });
    </script>
</body>

</html>