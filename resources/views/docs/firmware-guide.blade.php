@extends('layouts.app')

@section('title', 'Codegraph Firmware · Incubator.ino')

@php
    $lines = preg_split('/\R/u', $source);

    if ($lines !== [] && end($lines) === '') {
        array_pop($lines);
    }

    $totalLines = count($lines);

    $chunks = [
        [
            'label' => 'Pustaka',
            'title' => 'Dua belas library, satu firmware',
            'body' => 'Bagian ini memasang driver sensor, protokol 1-wire, I2C, penyimpanan flash, Wi-Fi, TLS, dan MQTT. Firmware memakai fungsi yang sudah diuji library, bukan menulis ulang protokolnya.',
            'effect' => 'Tanpa include ini, compiler tidak mengenal millis(), deserializeJson(), atau mqtt.publish().',
            'libraries' => ['Arduino.h', 'ArduinoJson v6', 'OneWire + DallasTemperature', 'DHT', 'LiquidCrystal_I2C', 'Wire', 'Preferences', 'WiFi', 'WiFiClientSecure', 'PubSubClient', 'driver/gpio.h', 'FreeRTOS dari Arduino.h'],
            'lines' => [
                [1, 'Arduino.h menyediakan millis(), delay(), pinMode(), digitalWrite(), dan Serial.printf().'],
                [2, 'ArduinoJson v6 menyediakan StaticJsonDocument, deserializeJson, serializeJson, JsonObject, dan JsonVariant.'],
                [3, 'DallasTemperature membaca DS18B20 lewat requestTemperatures() dan getTempCByIndex().'],
                [4, 'DHT membaca DHT22 lewat begin(), readTemperature(), dan readHumidity().'],
                [5, 'LiquidCrystal_I2C mengontrol LCD lewat init(), backlight(), setCursor(), dan print().'],
                [6, 'OneWire adalah lapisan protokol 1-wire yang dipakai DallasTemperature.'],
                [7, 'Preferences menyimpan config ke flash NVS lewat begin(), getFloat(), putFloat(), dan end().'],
                [8, 'PubSubClient menyediakan MQTT: setServer(), connect(), publish(), subscribe(), loop(), dan setCallback().'],
                [9, 'WiFi mengatur mode STA, begin(), status(), dan reconnect().'],
                [10, 'WiFiClientSecure membawa TLS. setInsecure() menonaktifkan verifikasi sertifikat.'],
                [11, 'Wire membersihkan bus I2C lewat begin(). LCD memakai bus ini.'],
                [12, 'driver/gpio.h memberi akses gpio_set_level(), dipakai sebelum pinMode saat boot.'],
            ],
        ],
        [
            'label' => 'Konstanta',
            'title' => 'Identitas, pin, dan batas aman',
            'body' => 'Bagian ini menentukan nama perangkat, kredensial, nomor GPIO, periode sampel, dan batas keselamatan. Nilainya tetap selama kompilasi.',
            'effect' => 'Satu angka di sini mengubah seluruh perilaku. Contohnya INTERVAL_DHT 2500 juga menjadi periode PID.',
            'libraries' => ['Arduino.h'],
            'lines' => [
                [15, 'KODE_PERANGKAT dipakai sebagai client MQTT dan device_code di payload.'],
                [16, 'SSID Wi-Fi. Nilai ini tertanam di firmware.'],
                [17, 'Kata sandi Wi-Fi. Jangan commit kredensial asli.'],
                [18, 'HOST_MQTT adalah alamat broker. Port TLS diatur di setup, 8883.'],
                [19, 'Kata sandi MQTT masih kosong di source ini.'],
                [20, 'Alamat I2C LCD, 0x27.'],
                [21, 'RELAY_NYALA_LOW true berarti relay aktif-low. LOW berarti nyala.'],
                [23, 'constexpr berarti nilai tetap saat compile. uint8_t adalah bilangan bulat 0 sampai 255. DHT22 di GPIO 5.'],
                [24, 'DS18B20 di GPIO 4. Sensor ini mengukur suhu bayi.'],
                [26, 'enum memberi nama pada urutan angka: KIPAS 0, PEMANAS_1 1, PEMANAS_2 2, LAMPU_BIRU 3, JUMLAH_RELAY 4.'],
                [27, 'PIN_RELAY adalah array empat nomor GPIO. Urutannya sama dengan enum.'],
                [28, 'NAMA_RELAY adalah array string untuk kunci JSON fan, heater_1, heater_2, blue_light.'],
                [30, 'uint32_t adalah bilangan bulat 32-bit. 10000 ms sama dengan 10 detik.'],
                [31, 'DHT22 dibaca tiap 2500 ms. DHT22 butuh minimal 2 detik antar bacaan.'],
                [32, 'JEDA_RELAY 30 ms mencegah lonjakan arus saat relay berpindah.'],
                [33, 'float menyimpan bilangan desimal. 37.5 C adalah batas suhu bayi.'],
                [34, '38.0 C adalah batas suhu ruang.'],
                [35, 'INTEGRAL_MAKS 15 membatasi kontribusi suku integral.'],
            ],
        ],
        [
            'label' => 'State',
            'title' => 'Objek dan variabel bersama',
            'body' => 'Objek library dibuat sekali di awal. Variabel global menyimpan hasil sensor, config, state PID, dan relay. Dua core membaca sebagian variabel yang sama.',
            'effect' => 'Perubahan dari MQTT harus sampai ke loop kontrol. Sebaliknya, perubahan relay harus memicu telemetri.',
            'libraries' => ['OneWire + DallasTemperature', 'DHT', 'LiquidCrystal_I2C', 'WiFiClientSecure', 'PubSubClient', 'Preferences'],
            'lines' => [
                [38, 'OneWire memakai PIN_DS. DallasTemperature membaca sensor lewat objek ini.'],
                [39, 'sensorBayi adalah objek DallasTemperature.'],
                [40, 'sensorRuangan adalah objek DHT22.'],
                [41, 'LCD memakai alamat 0x27, 16 kolom, 2 baris.'],
                [42, 'klienAman adalah WiFiClientSecure, pembawa TLS.'],
                [43, 'mqtt adalah PubSubClient yang memakai klien TLS.'],
                [44, 'pref adalah Preferences untuk flash NVS.'],
                [45, 'Tiga String topik MQTT diisi di setup.'],
                [47, 'Hasil sensor disimpan di suhuBayi, suhuRuangan, dan kelembapan.'],
                [48, 'sensorBayiOk dan sensorRuanganOk dipakai pengaman.'],
                [49, 'sampelBaru menandai ada bacaan DHT22 baru.'],
                [51, 'Config awal: setpoint 33.5 C, kp 40, ki 0.5, kd 40.'],
                [52, 'lampuBiruAktif mengikuti config, bukan PID.'],
                [54, 'integralPid, suhuLalu, turunanFilter, dan outputPid adalah memory kerja PID.'],
                [55, 'pidSiap menandai sampel pertama sudah ada.'],
                [57, 'relayTarget adalah keinginan. relayAktif adalah kondisi pin yang benar-benar ditulis.'],
                [58, 'volatile memberi tahu compiler nilai bisa berubah dari core lain. int8_t adalah bilangan bulat -128 sampai 127.'],
                [59, 'modeManual menandai ada tidaknya override manual.'],
                [60, 'waktuMulaiManual dipakai timer 10 detik.'],
                [61, 'kirimSegera meminta telemetri dikirim sekarang.'],
            ],
        ],
        [
            'label' => 'Timer',
            'title' => 'millis() menggantikan delay()',
            'body' => 'sudahWaktunya tidak menghentikan program. Ia menyimpan waktu terakhir, lalu membandingkan selisihnya dengan jeda.',
            'effect' => 'Kontrol suhu tetap berjalan saat fungsi lain menunggu. Ini pola dasar firmware real-time.',
            'libraries' => ['Arduino.h'],
            'lines' => [
                [64, 'sudahWaktunya menerima uint32_t &terakhir. Tanda & membuatnya referensi, jadi perubahan di dalam fungsi ikut mengubah variabel pemanggil.'],
                [65, 'Jika now - terakhir lebih kecil dari jeda, fungsi kembali false. Selisih unsigned tetap benar saat millis() rollover.'],
                [66, 'Penanda waktu diperbarui hanya saat jeda tercapai.'],
                [70, '? : adalah ternary, bentuk singkat if-else. batasi mengunci nilai ke lo sampai hi.'],
                [72, 'Komentar ini menjelaskan aturan histeresis: nyala di batas atas, mati di batas bawah.'],
                [73, 'Di antara dua batas, fungsi mengembalikan kondisi relay sekarang.'],
                [74, 'Ternary bertingkat: nilai >= on, nilai <= off, atau state sekarang.'],
            ],
        ],
        [
            'label' => 'Flash',
            'title' => 'Config bertahan setelah mati',
            'body' => 'Preferences adalah penyimpanan key-value di flash ESP32. Namespace inc dipakai untuk semua kunci config.',
            'effect' => 'Setpoint dan tuning PID tidak hilang saat perangkat mati. Nilai baru dari sync ditulis kembali ke flash.',
            'libraries' => ['Preferences'],
            'lines' => [
                [77, 'muatConfig() dipanggil sekali di setup().'],
                [78, 'pref.begin("inc", true) membuka namespace inc secara read-only.'],
                [79, 'getFloat("sp", default) mengambil setpoint dari flash.'],
                [80, 'getFloat("kp", default) mengambil gain proporsional.'],
                [81, 'getFloat("ki", default) mengambil gain integral.'],
                [82, 'getFloat("kd", default) mengambil gain derivatif.'],
                [83, 'getBool("led", default) mengambil status lampu biru.'],
                [84, 'pref.end() menutup namespace.'],
                [87, 'simpanConfig() dipanggil saat config baru diterima lewat MQTT.'],
                [88, 'pref.begin("inc", false) membuka namespace untuk ditulis.'],
                [89, 'putFloat("sp") menulis setpoint. NVS melewati penulisan jika nilai sama.'],
                [90, 'putFloat("kp") menulis gain proporsional.'],
                [91, 'putFloat("ki") menulis gain integral.'],
                [92, 'putFloat("kd") menulis gain derivatif.'],
                [93, 'putBool("led") menulis status lampu biru.'],
                [94, 'pref.end() menutup namespace setelah penulisan.'],
            ],
        ],
        [
            'label' => 'Sensor',
            'title' => 'Dua sensor, dua peran',
            'body' => 'DS18B20 dibaca dengan pola minta lalu ambil. DHT22 dibaca berkala. Keduanya memakai teknik non-blocking agar loop tidak berhenti.',
            'effect' => 'Suhu ruang menjadi masukan PID. Suhu bayi dipakai pengaman overheat. Kelembapan hanya ditampilkan dan dikirim.',
            'libraries' => ['OneWire + DallasTemperature', 'DHT', 'Arduino.h'],
            'lines' => [
                [98, 'bacaSensor(now) dipanggil setiap iterasi loop().'],
                [99, 'static membuat tMinta dan tDht menyimpan nilai antar pemanggilan.'],
                [100, 'menunggu menandai konversi DS18B20 sedang berjalan.'],
                [101, 'gagalDht menghitung kegagalan DHT22 berturut-turut.'],
                [103, 'Komentar ini menjelaskan alasan 400 ms: resolusi 11-bit butuh sekitar 375 ms.'],
                [104, 'Jika belum menunggu, firmware boleh meminta konversi baru.'],
                [105, 'requestTemperatures() memulai konversi tanpa menunggu. tMinta dicatat.'],
                [106, 'Setelah 400 ms, hasil konversi boleh diambil.'],
                [107, 'menunggu dikembalikan ke false.'],
                [108, 'getTempCByIndex(0) mengambil suhu dari sensor pertama.'],
                [109, '&& berarti DAN. Rentang -50 sampai 84 menolak -127 (putus) dan 85 (power-on).'],
                [110, 'Jika valid, suhuBayi diperbarui.'],
                [114, 'sudahWaktunya menjaga jeda minimal DHT22.'],
                [115, 'readTemperature() dan readHumidity() membaca dua nilai sekaligus.'],
                [116, 'isnan() mendeteksi hasil baca yang gagal.'],
                [117, 'Jika berhasil, penghitung gagal dikembalikan ke nol.'],
                [118, 'sensorRuanganOk ditandai benar.'],
                [119, 'sampelBaru=true memicu hitungPid() di loop().'],
                [120, '++gagalDht menaikkan nilai dulu, lalu == 3 membandingkan. Dua kegagalan pertama masih ditoleransi.'],
                [121, 'Kegagalan ketiga berturut menandai sensor rusak.'],
                [122, 'sampelBaru tetap dipicu supaya PID mereset output.'],
            ],
        ],
        [
            'label' => 'PID',
            'title' => 'PID menghitung dari suhu ruang',
            'body' => 'PID menggabungkan tiga informasi: seberapa jauh suhu dari target, berapa lama kesalahan menumpuk, dan seberapa cepat suhu berubah.',
            'effect' => 'outputPid dibatasi -100 sampai +100. Nilai ini menentukan kapan pemanas dan kipas menyala.',
            'libraries' => ['Arduino.h'],
            'lines' => [
                [129, 'hitungPid() dipanggil hanya saat ada sampel DHT22 baru.'],
                [130, 'Jika sensor ruang rusak, integral, turunan, dan output direset.'],
                [132, 'error adalah setpoint dikurangi suhu ruang.'],
                [133, 'Sampel pertama dipakai sebagai acuan turunan.'],
                [136, '+= menambah nilai ke variabel sendiri. 0.3f adalah float. Turunan dihitung dari pengukuran, bukan setpoint, agar tidak ada lonjakan saat setpoint berubah.'],
                [137, 'suhuLalu menyimpan pembacaan sebelumnya.'],
                [140, 'Integral hanya dihitung jika ki lebih besar dari nol.'],
                [141, 'Batas integral adalah 15 dibagi ki. Ini anti-windup.'],
                [142, 'fabsf(error) < 1 berarti integral hanya aktif dekat target.'],
                [143, 'integralPid menumpuk error dari waktu ke waktu.'],
                [144, 'batasi() mengunci akumulasi agar tidak melampaui batas.'],
                [148, 'Rumus lengkap: kp kali error, ditambah ki kali integral, ditambah kd kali turunan, lalu di-clamp.'],
            ],
        ],
        [
            'label' => 'Relay',
            'title' => 'Relay, histeresis, dan fail-safe',
            'body' => 'aturRelay memutuskan state otomatis, lalu menerapkan override manual jika ada. Hanya satu pin yang diubah setiap 30 ms.',
            'effect' => 'Histeresis mencegah relay berkedip. Pengaman suhu tetap menang walau ada perintah uji.',
            'libraries' => ['Arduino.h'],
            'lines' => [
                [152, 'digitalWrite(pin, nyala == RELAY_NYALA_LOW ? LOW : HIGH) memilih level pin sesuai relay aktif-low.'],
                [155, 'terapkanRelay membatasi satu perubahan pin per 30 ms.'],
                [156, 'static terakhir menyimpan waktu perubahan terakhir.'],
                [157, 'Jika belum 30 ms, fungsi kembali tanpa menulis pin.'],
                [158, 'Loop memeriksa keempat relay.'],
                [159, 'Relay yang tidak berubah dilewati.'],
                [160, 'tulisRelay menerapkan nilai ke GPIO.'],
                [161, 'relayAktif diperbarui.'],
                [162, 'Waktu perubahan dicatat.'],
                [163, 'kirimSegera=true membuat dashboard mendapat status terbaru.'],
                [168, 'aturRelay dijalankan setiap loop().'],
                [169, 'static otomatis menyimpan state otomatis, terpisah dari override manual.'],
                [171, 'Override manual berakhir setelah 10 detik.'],
                [172, 'Semua perintah manual dikembalikan ke -1, artinya auto.'],
                [173, 'modeManual dimatikan.'],
                [176, 'Lampu biru mengikuti lampuBiruAktif, bukan hasil PID.'],
                [177, 'Jika sensor ruang valid, heater dan kipas dihitung.'],
                [178, 'Heater 1 menyala saat output minimal 20 dan mati saat maksimal 10.'],
                [179, 'Heater 2 menyala saat output minimal 60 dan mati saat maksimal 50.'],
                [180, 'Kipas memakai -outputPid. Kipas menyala saat ruang terlalu panas.'],
                [181, 'Jika sensor ruang rusak, semua aktuator otomatis dimatikan.'],
                [182, 'Heater 1, heater 2, dan kipas diset false.'],
                [185, 'Loop memeriksa override manual per relay.'],
                [186, 'm adalah perintah manual untuk relay ini.'],
                [187, 'Ternary bertingkat: jika m < 0 pakai otomatis, jika m == 1 nyala, selain itu mati.'],
                [190, 'Pengaman overheat diperiksa setelah override.'],
                [191, 'Batas: suhu bayi minimal 37.5 atau suhu ruang minimal 38.0.'],
                [192, 'Kedua pemanas dipaksa mati.'],
                [194, 'terapkanRelay menulis perubahan ke pin secara bertahap.'],
            ],
        ],
        [
            'label' => 'Sync masuk',
            'title' => 'JSON dibaca di callback MQTT',
            'body' => 'prosesPerintah menerima pesan sync dari Laravel. ArduinoJson mengurai objek config dan relay_overrides.',
            'effect' => 'Setpoint baru langsung masuk ke variabel PID. Tombol uji relay langsung masuk ke perintahManual.',
            'libraries' => ['ArduinoJson v6', 'Preferences', 'PubSubClient'],
            'lines' => [
                [233, 'prosesPerintah dipanggil dari callback PubSubClient di Core 0.'],
                [234, 'StaticJsonDocument<512> menyediakan buffer tetap di stack, tanpa alokasi heap.'],
                [235, 'deserializeJson mengubah string JSON menjadi objek.'],
                [237, 'doc["config"] mengambil bagian konfigurasi.'],
                [238, 'isNull() memeriksa apakah bagian config ada.'],
                [239, 'Operator | mengambil nilai JSON atau default jika kunci tidak ada. Hasilnya dibatasi 28 sampai 37 derajat.'],
                [240, 'blue_led_enabled diambil dari JSON.'],
                [241, 'kp dibatasi 0 sampai 500.'],
                [242, 'ki dibatasi 0 sampai 100.'],
                [243, 'kd dibatasi 0 sampai 500.'],
                [244, 'simpanConfig menulis semua nilai ke flash.'],
                [245, 'kirimSegera meminta telemetri baru.'],
                [248, 'doc["relay_overrides"] mengambil perintah uji relay.'],
                [249, 'isNull() memeriksa apakah bagian override ada.'],
                [250, 'ada menandai minimal satu kunci override ditemukan.'],
                [251, 'Loop memeriksa keempat relay.'],
                [252, 'JsonVariant menampung nilai relay dari JSON.'],
                [253, 'isNull() memilih -1. as<bool>() menerima true/false maupun 1/0.'],
                [254, 'ada |= !v.isNull() mencatat apakah ada kunci yang valid.'],
                [256, 'waktuMulaiManual dicatat untuk timer 10 detik.'],
                [257, 'modeManual aktif hanya jika ada override.'],
            ],
        ],
        [
            'label' => 'Telemetri',
            'title' => 'Payload telemetri disusun',
            'body' => 'kirimData merakit JSON berisi hasil sensor, state relay, status sensor, config aktif, dan nilai PID.',
            'effect' => 'Laravel memakai payload ini untuk menyimpan telemetri dan menentukan balasan sync.',
            'libraries' => ['ArduinoJson v6', 'PubSubClient', 'Arduino.h'],
            'lines' => [
                [261, 'kirimData() hanya bekerja jika MQTT sudah terhubung.'],
                [262, 'connected() mencegah publish saat koneksi putus.'],
                [264, 'StaticJsonDocument<768> menampung payload keluar.'],
                [265, 'device_code dipakai Laravel untuk mencari incubator.'],
                [266, 'temperature_object dikirim 0 jika sensor bayi gagal. roundf membulatkan dua desimal.'],
                [267, 'temperature_room dikirim 0 jika DHT22 gagal.'],
                [268, 'humidity dibulatkan satu desimal.'],
                [269, 'pid_output ikut dikirim supaya mudah dianalisis.'],
                [270, 'setpoint aktual ikut dikirim.'],
                [271, 'kp, ki, dan kd ikut dikirim.'],
                [272, 'manual menandai sedang ada override.'],
                [273, 'doc["relays"][NAMA_RELAY[i]] membuat objek JSON bertingkat.'],
                [274, 'sensor_status.ds18b20 berisi status sensor bayi.'],
                [275, 'sensor_status.dht22 berisi status sensor ruang.'],
                [276, 'uptime_s adalah waktu sejak boot dalam detik.'],
                [278, 'payload adalah buffer char untuk hasil serialisasi.'],
                [279, 'serializeJson mengubah dokumen menjadi string.'],
                [280, 'mqtt.publish mengirim payload ke topik telemetri.'],
            ],
        ],
        [
            'label' => 'Jaringan',
            'title' => 'Wi-Fi, MQTT, dan LWT',
            'body' => 'jagaKoneksi memastikan Wi-Fi dan MQTT tetap hidup. LWT adalah pesan terakhir yang dikirim broker saat perangkat putus.',
            'effect' => 'Status online dan offline di Laravel muncul tanpa menunggu telemetri berikutnya.',
            'libraries' => ['WiFi', 'PubSubClient', 'WiFiClientSecure', 'FreeRTOS dari Arduino.h', 'Arduino.h'],
            'lines' => [
                [283, 'jagaKoneksi(now) dijalankan task jaringan.'],
                [284, 'static cobaWifi dan cobaMqtt menyimpan waktu percobaan terakhir.'],
                [286, 'Jika Wi-Fi tidak tersambung, fungsi mencoba reconnect.'],
                [287, 'WiFi.reconnect() dipanggil tiap 5000 ms.'],
                [288, 'return menghentikan fungsi sampai Wi-Fi tersambung.'],
                [290, 'Jika MQTT sudah connected, jalankan mqtt.loop().'],
                [292, 'Percobaan MQTT juga dibatasi 5000 ms.'],
                [293, 'mqtt.connect menerima client id, user, password, topik LWT, QoS, retain, dan payload.'],
                [294, 'Payload LWT berisi online false.'],
                [295, 'Status online dipublish dengan retained=true.'],
                [296, 'Perangkat subscribe topik sync dengan QoS 1.'],
                [297, 'kirimSegera meminta telemetri pertama.'],
                [301, 'tugasJaringan adalah task FreeRTOS yang dipasang di Core 0.'],
                [302, 'waktuKirim menyimpan jadwal telemetri.'],
                [303, 'for (;;) membuat task berjalan terus.'],
                [304, 'now diambil dari millis().'],
                [305, 'jagaKoneksi memelihara Wi-Fi dan MQTT.'],
                [306, 'Telemetri dikirim tiap 3000 ms atau saat kirimSegera.'],
                [307, 'kirimSegera dikembalikan ke false.'],
                [308, 'waktuKirim diperbarui.'],
                [309, 'kirimData() dipanggil dari task ini.'],
                [311, 'vTaskDelay(10) memberi jeda agar task tidak menyita CPU.'],
                [352, 'xTaskCreatePinnedToCore memasang task jaringan di Core 0.'],
            ],
        ],
        [
            'label' => 'Tampilan',
            'title' => 'LCD hanya ditulis saat berubah',
            'body' => 'LCD dan Serial adalah lapisan tampilan. Keduanya tidak memengaruhi keputusan kontrol.',
            'effect' => 'Cache teks mencegah LCD berkedip dan menghemat waktu tulis I2C.',
            'libraries' => ['LiquidCrystal_I2C', 'Arduino.h'],
            'lines' => [
                [198, 'tulisLcd adalah fungsi variadic. Parameter ... menerima format dan argumen tambahan.'],
                [199, 'cache menyimpan teks terakhir untuk dua baris.'],
                [201, 'va_list dan vsnprintf menyusun teks dari format.'],
                [202, 'snprintf memotong teks menjadi maksimal 16 karakter.'],
                [203, 'strcmp membandingkan teks baru dengan cache. Jika sama, tidak ada penulisan.'],
                [204, 'strcpy menyimpan teks baru ke cache.'],
                [205, 'setCursor memilih kolom dan baris.'],
                [206, 'print menulis teks ke LCD.'],
                [214, 'perbaruiLcd dipanggil tiap 500 ms.'],
                [215, 'millis()/3000 & 1 berganti nilai tiap 3 detik.'],
                [216, 'Saat ganjil, LCD menampilkan kp dan ki.'],
                [217, 'Baris kedua menampilkan kd dan setpoint.'],
                [218, 'Saat genap, LCD menampilkan suhu.'],
                [219, 'Dua buffer char disiapkan untuk suhu.'],
                [220, 'Baris pertama menampilkan suhu bayi.'],
                [221, 'Baris kedua menampilkan suhu ruang.'],
            ],
        ],
        [
            'label' => 'Setup',
            'title' => 'Boot yang aman',
            'body' => 'setup() menyiapkan relay, config, LCD, sensor, topik MQTT, TLS, dan task jaringan. Urutannya disengaja.',
            'effect' => 'Relay ditulis MATI sebelum menjadi output. Ini mencegah pemanas menyala sesaat saat boot.',
            'libraries' => ['driver/gpio.h', 'Preferences', 'Wire', 'LiquidCrystal_I2C', 'OneWire + DallasTemperature', 'DHT', 'WiFi', 'WiFiClientSecure', 'PubSubClient', 'FreeRTOS dari Arduino.h', 'Arduino.h'],
            'lines' => [
                [316, 'setup() berjalan sekali saat perangkat menyala.'],
                [317, 'Serial dibuka pada 115200 baud.'],
                [319, 'Komentar ini menjelaskan alasan urutan relay.'],
                [320, 'Loop menyiapkan keempat pin relay.'],
                [321, 'gpio_set_level menulis level MATI sebelum pin menjadi output. Cast (gpio_num_t) mengubah angka ke tipe enum GPIO.'],
                [322, 'pinMode menjadikan pin sebagai output.'],
                [325, 'muatConfig membaca setpoint dan gain dari flash.'],
                [327, 'Wire.begin() menyalakan bus I2C.'],
                [328, 'lcd.init() menyiapkan LCD.'],
                [329, 'lcd.backlight() menyalakan lampu latar.'],
                [331, 'setWaitForConversion(false) membuat konversi DS18B20 tidak memblokir loop.'],
                [332, 'sensorBayi.begin() menyalakan driver DS18B20.'],
                [333, 'Resolusi 11 bit dipilih.'],
                [334, 'sensorRuangan.begin() menyalakan DHT22.'],
                [336, 'Prefix topik dibentuk dari kode perangkat.'],
                [337, 'Topik sync untuk perintah dari Laravel.'],
                [338, 'Topik telemetri untuk data dari ESP32.'],
                [339, 'Topik status untuk online dan offline.'],
                [341, 'setInsecure() memakai TLS tanpa verifikasi sertifikat. Ini celah MITM pada prototipe.'],
                [342, 'Broker MQTT diatur ke port 8883.'],
                [343, 'Buffer MQTT 1024 byte cukup untuk payload JSON.'],
                [344, 'Keepalive 30 detik.'],
                [345, 'Socket timeout 5 detik.'],
                [346, 'Lambda tanpa nama menjadi callback saat pesan sync masuk.'],
                [348, 'WiFi diatur ke mode station.'],
                [349, 'Modem sleep dimatikan agar perintah lebih responsif.'],
                [350, 'Delay 200 ms memberi waktu supply stabil sebelum radio aktif.'],
                [351, 'WiFi.begin menyambung ke SSID.'],
                [352, 'Task jaringan dipasang di Core 0.'],
            ],
        ],
        [
            'label' => 'Loop',
            'title' => 'Core 1 menjalankan kontrol',
            'body' => 'loop() adalah siklus utama ESP32. Sensor dibaca, PID dihitung, relay diatur, lalu LCD dan Serial diperbarui.',
            'effect' => 'Kontrol tetap berjalan walau Wi-Fi putus, karena semua keputusan diambil di perangkat.',
            'libraries' => ['Arduino.h'],
            'lines' => [
                [355, 'loop() berjalan di Core 1.'],
                [356, 'static waktuLcd dan waktuSerial menyimpan jadwal terakhir.'],
                [357, 'now diisi dari millis(), jam sejak perangkat boot.'],
                [359, 'bacaSensor membaca DS18B20 dan DHT22.'],
                [360, 'hitungPid hanya dipanggil saat ada sampel baru.'],
                [361, 'aturRelay menentukan dan menulis state relay.'],
                [363, 'LCD diperbarui tiap 500 ms.'],
                [364, 'Serial mencetak ringkasan tiap 2000 ms.'],
                [366, 'delay(10) hanya jeda kecil, bukan penentu jadwal.'],
            ],
        ],
    ];

    $libraries = [
        ['name' => 'Arduino.h', 'role' => 'Fungsi dasar board', 'functions' => ['millis()', 'delay()', 'pinMode()', 'digitalWrite()', 'digitalRead()', 'Serial.printf()', 'map()', 'constrain()']],
        ['name' => 'ArduinoJson v6', 'role' => 'Parser JSON', 'functions' => ['StaticJsonDocument', 'DynamicJsonDocument', 'deserializeJson()', 'serializeJson()', 'JsonObject', 'JsonVariant', 'isNull()', 'as<T>()', 'operator |']],
        ['name' => 'OneWire + DallasTemperature', 'role' => 'Driver DS18B20', 'functions' => ['begin()', 'setWaitForConversion(false)', 'setResolution(11)', 'requestTemperatures()', 'getTempCByIndex(0)']],
        ['name' => 'DHT', 'role' => 'Driver DHT22', 'functions' => ['begin()', 'readTemperature()', 'readHumidity()', 'isnan() dari Arduino']],
        ['name' => 'LiquidCrystal_I2C', 'role' => 'Driver LCD', 'functions' => ['init()', 'backlight()', 'setCursor()', 'print()']],
        ['name' => 'Wire', 'role' => 'Bus I2C', 'functions' => ['begin()', 'beginTransmission()', 'write()', 'endTransmission()']],
        ['name' => 'Preferences', 'role' => 'Penyimpanan flash', 'functions' => ['begin(namespace, readOnly)', 'getFloat()', 'getBool()', 'putFloat()', 'putBool()', 'end()']],
        ['name' => 'WiFi', 'role' => 'Koneksi Wi-Fi', 'functions' => ['mode()', 'setSleep()', 'begin()', 'status()', 'reconnect()', 'WL_CONNECTED']],
        ['name' => 'WiFiClientSecure', 'role' => 'Transport TLS', 'functions' => ['setInsecure()', 'setCACert()', 'connect()', 'stop()']],
        ['name' => 'PubSubClient', 'role' => 'Klien MQTT', 'functions' => ['setServer()', 'setBufferSize()', 'setKeepAlive()', 'setSocketTimeout()', 'setCallback()', 'connect() dengan LWT', 'publish()', 'subscribe()', 'loop()', 'connected()']],
        ['name' => 'driver/gpio.h', 'role' => 'Kontrol GPIO rendah', 'functions' => ['gpio_set_level()', 'gpio_num_t']],
        ['name' => 'FreeRTOS dari Arduino.h', 'role' => 'Task dua core', 'functions' => ['xTaskCreatePinnedToCore()', 'vTaskDelay()', 'pdMS_TO_TICKS()']],
    ];

    $loopStages = [
        ['label' => 'Setpoint', 'body' => 'Target 33.5 C. Nilai ini bisa diubah dari dashboard lewat MQTT, lalu disimpan ke flash.', 'nodes' => ['setpoint'], 'edges' => ['setpoint']],
        ['label' => 'Error', 'body' => 'error = setpoint - suhu ruang. Positif berarti ruang terlalu dingin. Negatif berarti ruang terlalu panas.', 'nodes' => ['error'], 'edges' => ['error']],
        ['label' => 'PID', 'body' => 'P, I, dan D dihitung dari error. Hasilnya dibatasi -100 sampai +100.', 'nodes' => ['pid'], 'edges' => ['pid']],
        ['label' => 'Relay', 'body' => 'Output PID diterjemahkan lewat histeresis. Heater 1 mulai di 20, heater 2 di 60, kipas saat output negatif.', 'nodes' => ['relay'], 'edges' => ['relay']],
        ['label' => 'Aktuator', 'body' => 'Pemanas menambah panas. Kipas membuang panas. Ruang berubah, lalu diukur lagi.', 'nodes' => ['heater', 'room'], 'edges' => ['heater']],
        ['label' => 'DHT22', 'body' => 'DHT22 membaca suhu ruang. Nilai ini kembali ke perhitungan error. Di sinilah loop tertutup.', 'nodes' => ['dht'], 'edges' => ['feedback']],
    ];

    $pinMap = [
        ['pin' => 'GPIO 4', 'part' => 'DS18B20', 'role' => 'Suhu bayi, pengaman 37.5 C', 'kind' => 'sensor'],
        ['pin' => 'GPIO 5', 'part' => 'DHT22', 'role' => 'Suhu ruang dan RH, masukan PID', 'kind' => 'sensor'],
        ['pin' => 'GPIO 21/22', 'part' => 'LCD I2C 0x27', 'role' => 'SDA dan SCL, tampilan lokal', 'kind' => 'display'],
        ['pin' => 'GPIO 14', 'part' => 'Relay kipas', 'role' => 'Membuang panas saat output negatif', 'kind' => 'actuator'],
        ['pin' => 'GPIO 26', 'part' => 'Relay heater 1', 'role' => 'Pemanas utama, ambang 20/10', 'kind' => 'actuator'],
        ['pin' => 'GPIO 27', 'part' => 'Relay heater 2', 'role' => 'Pemanas kedua, ambang 60/50', 'kind' => 'actuator'],
        ['pin' => 'GPIO 25', 'part' => 'Relay lampu biru', 'role' => 'Lampu status dari config', 'kind' => 'actuator'],
    ];

    $quickStarts = [
        ['label' => 'Include dan library', 'step' => 0, 'detail' => 'Baris 1 sampai 12'],
        ['label' => 'Sensor dan data', 'step' => 5, 'detail' => 'Baris 98 sampai 122'],
        ['label' => 'PID dan output', 'step' => 6, 'detail' => 'Baris 129 sampai 148'],
        ['label' => 'Relay dan fail-safe', 'step' => 7, 'detail' => 'Baris 152 sampai 194'],
        ['label' => 'Loop kontrol', 'step' => 13, 'detail' => 'Baris 355 sampai 366'],
    ];


    function highlightInoLine(string $line): string
    {
        if (str_starts_with(trim($line), '#include')) {
            return preg_replace_callback(
                '~(&lt;[^&]+&gt;)~',
                static fn (array $match): string => '<span class="tok-library">'.$match[1].'</span>',
                e($line),
            ) ?? e($line);
        }

        $pattern = '~("(?:[^"\\\\]|\\\\.)*"|//.*$)|\b(\d+(?:\.\d+)?)[fF]?\b|\b([A-Z][A-Z0-9_]{2,})\b|\b(if|else|for|while|return|void|bool|float|uint8_t|uint32_t|constexpr|static|true|false|nullptr|auto)\b~';

        preg_match_all($pattern, $line, $matches, PREG_OFFSET_CAPTURE);

        $out = [];
        $offset = 0;

        foreach ($matches[0] as $i => $match) {
            [$text, $position] = $match;

            if ($position > $offset) {
                $out[] = e(substr($line, $offset, $position - $offset));
            }

            $class = match (true) {
                $matches[1][$i][1] >= 0 && str_starts_with($text, '//') => 'tok-comment',
                $matches[1][$i][1] >= 0 => 'tok-string',
                $matches[2][$i][1] >= 0 => 'tok-number',
                $matches[3][$i][1] >= 0 => 'tok-const',
                default => 'tok-keyword',
            };

            $out[] = '<span class="'.$class.'">'.e($text).'</span>';
            $offset = $position + strlen($text);
        }

        $out[] = e(substr($line, $offset));

        return implode('', $out);
    }

    $highlightLines = [];
    foreach ($chunks as $chunk) {
        foreach ($chunk['lines'] as [$number, $note]) {
            $highlightLines[$number] = highlightInoLine($lines[$number - 1] ?? '');
        }
    }
@endphp

@section('content')
    <div class="guide-page pb-16">
        <style>
            .guide-page .btn-secondary {
                border-color: #7f8a84;
            }

            .guide-page .btn-sm {
                min-height: 44px;
            }

            .loop-node rect {
                fill: #fffefa;
                stroke: #7f8a84;
                stroke-width: 1.5;
                transition: fill 180ms ease, stroke 180ms ease;
            }

            .loop-node .loop-title {
                fill: #16211d;
                font-size: 12px;
                font-weight: 700;
            }

            .loop-node .loop-sub {
                fill: #5d6a64;
                font-size: 9px;
            }

            .loop-node.is-active rect {
                fill: #0d6b55;
                stroke: #0d6b55;
            }

            .loop-node.is-active .loop-title,
            .loop-node.is-active .loop-sub {
                fill: #fff;
            }

            .loop-edge {
                fill: none;
                stroke: #7f8a84;
                stroke-width: 1.5;
                transition: stroke 180ms ease, stroke-width 180ms ease;
            }

            .loop-edge.is-active {
                stroke: #0d6b55;
                stroke-width: 2.4;
            }

            .loop-stage {
                display: flex;
                min-height: 44px;
                align-items: center;
                gap: 0.55rem;
                border: 1px solid #7f8a84;
                border-radius: 0.75rem;
                background: #fffefa;
                padding: 0.5rem 0.65rem;
                color: #5d6a64;
                transition: background-color 150ms ease, border-color 150ms ease, color 150ms ease;
            }

            .loop-stage:hover {
                border-color: #5d6a64;
                color: #16211d;
            }

            .loop-stage.is-current {
                border-color: #0d6b55;
                background: #e6f1ed;
                color: #0d6b55;
            }

            .schematic-node rect {
                stroke-width: 1.5;
            }

            .schematic-node .node-title {
                fill: #16211d;
                font-size: 12px;
                font-weight: 700;
            }

            .schematic-node .node-sub {
                fill: #5d6a64;
                font-size: 9px;
            }

            .schematic-sensor rect {
                fill: #e6f1ed;
                stroke: #0d6b55;
            }

            .schematic-actuator rect {
                fill: #fbf1dd;
                stroke: #8a5a00;
            }

            .schematic-board rect {
                fill: #fffefa;
                stroke: #16211d;
            }

            .schematic-network rect {
                fill: #fffefa;
                stroke: #5d6a64;
                stroke-dasharray: 4 3;
            }

            .schematic-wire {
                fill: none;
                stroke-width: 1.5;
            }

            .schematic-wire-sensor { stroke: #0d6b55; }
            .schematic-wire-actuator { stroke: #8a5a00; }
            .schematic-wire-network { stroke: #5d6a64; stroke-dasharray: 5 4; }

            .term-dialog {
                width: 100%;
                max-width: none;
                height: 100dvh;
                max-height: none;
                margin: 0;
                padding: 0;
                border: 0;
                background: #0b1512;
                color: #dce8e3;
            }

            .term-dialog[open] {
                display: flex;
                flex-direction: column;
            }

            .term-dialog:modal {
                overscroll-behavior: contain;
            }

            .term-dialog::backdrop {
                overflow: hidden;
                overscroll-behavior: contain;
                background: #0b1512;
            }

            .term-shell {
                display: flex;
                min-height: 0;
                flex: 1 1 auto;
                flex-direction: column;
            }

            .term-top {
                display: flex;
                flex: 0 0 auto;
                flex-wrap: wrap;
                align-items: center;
                justify-content: space-between;
                gap: 0.65rem;
                border-bottom: 1px solid rgb(255 255 255 / 0.1);
                padding: 0.7rem 1rem;
            }

            .term-kicker {
                font-family: var(--font-mono);
                font-size: 0.65rem;
                font-weight: 600;
                letter-spacing: 0.08em;
                text-transform: uppercase;
                color: #9eb5ab;
            }

            .term-body {
                display: grid;
                min-height: 0;
                flex: 1 1 auto;
                grid-template-rows: minmax(0, 1.15fr) minmax(0, 0.85fr);
            }

            .term-code {
                display: flex;
                min-height: 0;
                flex-direction: column;
                border-bottom: 1px solid rgb(255 255 255 / 0.1);
            }

            .term-scroll {
                min-height: 0;
                overflow: auto;
                overscroll-behavior: contain;
                scrollbar-gutter: stable;
            }

            .term-explain {
                min-height: 0;
                min-width: 0;
                background: #0d1915;
            }

            .term-explain [data-term-line-note] {
                white-space: pre-line;
                overflow-wrap: anywhere;
            }

            .term-code-head {
                display: flex;
                flex: 0 0 auto;
                flex-wrap: wrap;
                align-items: center;
                justify-content: space-between;
                gap: 0.5rem;
                border-bottom: 1px solid rgb(255 255 255 / 0.08);
                padding: 0.55rem 1rem;
            }

            .term-btn {
                display: inline-flex;
                min-height: 44px;
                align-items: center;
                justify-content: center;
                border: 1px solid rgb(255 255 255 / 0.35);
                border-radius: 0.6rem;
                background: transparent;
                padding: 0.35rem 0.65rem;
                color: #dce8e3;
                font-size: 0.72rem;
                font-weight: 600;
                transition: background-color 150ms ease, border-color 150ms ease;
            }

            .term-btn:hover {
                border-color: rgb(255 255 255 / 0.5);
                background: rgb(255 255 255 / 0.06);
            }

            .term-btn-nav {
                min-width: 44px;
                padding-inline: 0.5rem;
            }

            .term-btn-close {
                border-color: #6fd1ae;
                background: #6fd1ae;
                color: #0b1512;
            }

            .term-btn-close:hover {
                background: #8fe0c3;
            }

            .term-select {
                min-height: 44px;
                max-width: 11rem;
                border: 1px solid rgb(255 255 255 / 0.35);
                border-radius: 0.6rem;
                background: #101a17;
                padding: 0.3rem 0.5rem;
                color: #dce8e3;
                font-size: 0.72rem;
            }

            .term-row {
                display: grid;
                width: 100%;
                min-width: max-content;
                grid-template-columns: 3.25rem minmax(max-content, 1fr);
                border: 0;
                background: transparent;
                padding: 0;
                color: inherit;
                font: inherit;
                text-align: left;
                transition: background-color 150ms ease;
            }

            .term-row .term-num {
                position: sticky;
                left: 0;
                padding-right: 0.75rem;
                background: #101a17;
                color: #7f978d;
                text-align: right;
                user-select: none;
            }

            .term-row .term-text {
                white-space: pre;
                padding-right: 1.5rem;
            }

            .term-row.is-active {
                background: rgb(111 209 174 / 0.16);
                box-shadow: inset 2px 0 0 #6fd1ae;
            }

            .term-row.is-active .term-num {
                color: #dce8e3;
                font-weight: 700;
            }

            .term-dialog :focus-visible {
                outline-color: #6fd1ae;
            }

            .term-row:focus-visible {
                outline-offset: -2px;
            }

            .term-row.is-static {
                cursor: default;
            }

            .term-code-surface {
                margin: 0;
                background: #101a17;
                color: #dce8e3;
                font-family: var(--font-mono);
                font-size: 0.75rem;
                line-height: 1.65;
                padding-block: 0.5rem;
            }

            .tok-comment { color: #88a69b; }
            .tok-string { color: #a8d5ba; }
            .tok-number { color: #e7b46a; }
            .tok-const { color: #8fc9e8; }
            .tok-keyword { color: #d7a9e6; }
            .tok-library { color: #6fd1ae; }

            .pid-bar {
                height: 0.5rem;
                overflow: hidden;
                border-radius: 999px;
                background: #e5e0d6;
            }

            .pid-fill {
                height: 100%;
                border-radius: 999px;
                background: #0d6b55;
                transform: scaleX(0);
                transform-origin: left center;
                transition: transform 180ms var(--ease-out-quart);
            }

            .pid-fill.is-negative {
                background: #8a5a00;
            }

            @media (max-width: 359px) {
                .term-select {
                    max-width: 7.5rem;
                }
            }

            @media (max-width: 1023px) {
                .term-row:not(.is-static) {
                    min-height: 44px;
                    align-items: center;
                }
            }

            @media (min-width: 640px) {
                .term-btn-nav {
                    min-width: auto;
                    padding-inline: 0.65rem;
                }
            }

            @media (min-width: 1024px) {
                .term-body {
                    grid-template-columns: minmax(0, 1.55fr) minmax(340px, 0.85fr);
                    grid-template-rows: minmax(0, 1fr);
                }

                .term-code {
                    border-right: 1px solid rgb(255 255 255 / 0.1);
                    border-bottom: 0;
                }

                .term-code-surface {
                    font-size: 0.8125rem;
                }
            }

            @media (prefers-reduced-motion: no-preference) {
                .term-dialog[open] {
                    opacity: 1;
                    transform: translateY(0);
                    transition: opacity 180ms var(--ease-out-quart), transform 180ms var(--ease-out-quart);
                }

                @starting-style {
                    .term-dialog[open] {
                        opacity: 0;
                        transform: translateY(8px);
                    }
                }
            }
        </style>

        <section class="pt-2 sm:pt-6" aria-labelledby="guide-title">
            <div class="grid gap-10 lg:grid-cols-[minmax(0,1.35fr)_minmax(260px,0.65fr)] lg:items-start">
                <div>
                    <p class="eyebrow">Panduan firmware · Incubator.ino</p>
                    <h1 id="guide-title" class="mt-4 max-w-3xl break-words text-balance text-4xl font-semibold leading-tight tracking-[-0.035em] sm:text-5xl">
                        Belajar Incubator.ino dari satu baris kode.
                    </h1>
                    <p class="mt-5 max-w-2xl text-pretty text-base leading-relaxed text-ink-soft sm:text-lg">
                        Pilih topik, buka terminal, lalu ikuti hubungan antara sintaks, library, sensor, dan relay. Penjelasan muncul di samping kode yang dipilih.
                    </p>
                    <div class="mt-7 flex flex-col gap-3 sm:flex-row">
                        <button type="button" class="btn btn-primary" data-open-terminal data-step="0">Mulai dari include</button>
                        <a href="#terminal" class="btn btn-secondary">Lihat semua topik</a>
                    </div>
                    <dl class="mt-8 grid max-w-xl grid-cols-[repeat(auto-fit,minmax(7rem,1fr))] gap-4 border-t border-line pt-4">
                        <div>
                            <dt class="eyebrow">Source</dt>
                            <dd class="mt-1 font-mono text-sm">{{ $totalLines }} baris</dd>
                        </div>
                        <div>
                            <dt class="eyebrow">Terminal</dt>
                            <dd class="mt-1 font-mono text-sm">{{ count($chunks) }} langkah</dd>
                        </div>
                        <div>
                            <dt class="eyebrow">Transport</dt>
                            <dd class="mt-1 font-mono text-sm">MQTT TLS</dd>
                        </div>
                    </dl>
                </div>

                <div class="border-y border-line py-4">
                    <p class="px-1 text-xs font-semibold text-ink">Urutan belajar</p>
                    <ol class="mt-3 space-y-3 text-sm leading-relaxed text-ink-soft">
                        <li><span class="mr-2 font-mono text-xs text-accent">01</span>Kenali include dan fungsi library.</li>
                        <li><span class="mr-2 font-mono text-xs text-accent">02</span>Ikuti sensor sampai keputusan relay.</li>
                        <li><span class="mr-2 font-mono text-xs text-accent">03</span>Uji PID dengan angka yang nyata.</li>
                    </ol>
                    <p class="mt-4 border-t border-line pt-3 text-xs leading-relaxed text-ink-soft">Diagram dan simulator berada setelah terminal sebagai alat bantu, bukan menu utama.</p>
                </div>
            </div>
        </section>

        <section id="alur" class="scroll-mt-24 border-t border-line pt-10 sm:pt-14" aria-labelledby="alur-title">
            <div class="grid gap-4 border-t border-line pt-8 md:grid-cols-[10rem_minmax(0,1fr)]">
                <p class="eyebrow">01 · Alur kendali</p>
                <div>
                    <h2 id="alur-title" class="max-w-3xl text-balance text-3xl font-semibold tracking-[-0.035em] sm:text-4xl">Lingkaran kecil yang menjaga suhu.</h2>
                    <p class="mt-3 max-w-2xl text-pretty text-sm leading-relaxed text-ink-soft sm:text-base">
                        PID tidak membaca masa depan. Ia membandingkan suhu sekarang dengan target, lalu mengoreksi pemanas sedikit demi sedikit. Setiap koreksi diukur lagi pada siklus berikutnya.
                    </p>
                </div>
            </div>

            <div class="mt-8 grid gap-5 lg:grid-cols-[minmax(0,1.35fr)_minmax(280px,0.65fr)]">
                <div class="card overflow-hidden">
                    <div class="p-3 sm:p-5">
                    <svg viewBox="0 0 760 440" class="hidden h-auto w-full md:block" role="img" aria-labelledby="loop-svg-title loop-svg-desc">
                        <title id="loop-svg-title">Diagram loop tertutup PID inkubator</title>
                        <desc id="loop-svg-desc">Setpoint dibandingkan dengan suhu ruang menjadi error. Error masuk ke PID, lalu relay, pemanas, dan ruang. DHT22 mengukur ruang dan mengirim umpan balik.</desc>
                        <defs>
                            <marker id="loop-arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
                                <path d="M 0 0 L 10 5 L 0 10 z" fill="#7f8a84" />
                            </marker>
                        </defs>

                        <path class="loop-edge" data-loop-edge="setpoint" d="M 470 51 C 520 51, 540 80, 560 110" marker-end="url(#loop-arrow)" />
                        <path class="loop-edge" data-loop-edge="error" d="M 640 150 L 640 186" marker-end="url(#loop-arrow)" />
                        <path class="loop-edge" data-loop-edge="pid" d="M 640 240 L 640 276" marker-end="url(#loop-arrow)" />
                        <path class="loop-edge" data-loop-edge="relay" d="M 640 330 C 640 360, 560 375, 470 375" marker-end="url(#loop-arrow)" />
                        <path class="loop-edge" data-loop-edge="heater" d="M 290 375 C 240 375, 200 350, 200 330" marker-end="url(#loop-arrow)" />
                        <path class="loop-edge" data-loop-edge="feedback" d="M 115 276 L 115 150" marker-end="url(#loop-arrow)" />
                        <path class="loop-edge" data-loop-edge="feedback" d="M 200 123 L 560 123" marker-end="url(#loop-arrow)" />

                        <g class="loop-node" data-loop-node="setpoint">
                            <rect x="290" y="24" width="180" height="54" rx="12" />
                            <text class="loop-title" x="380" y="47" text-anchor="middle">Setpoint</text>
                            <text class="loop-sub" x="380" y="64" text-anchor="middle">target 33.5 C</text>
                        </g>
                        <g class="loop-node" data-loop-node="error">
                            <rect x="560" y="96" width="160" height="54" rx="12" />
                            <text class="loop-title" x="640" y="119" text-anchor="middle">Error</text>
                            <text class="loop-sub" x="640" y="136" text-anchor="middle">target - ruang</text>
                        </g>
                        <g class="loop-node" data-loop-node="pid">
                            <rect x="560" y="186" width="160" height="54" rx="12" />
                            <text class="loop-title" x="640" y="209" text-anchor="middle">PID</text>
                            <text class="loop-sub" x="640" y="226" text-anchor="middle">P + I + D</text>
                        </g>
                        <g class="loop-node" data-loop-node="relay">
                            <rect x="560" y="276" width="160" height="54" rx="12" />
                            <text class="loop-title" x="640" y="299" text-anchor="middle">Relay</text>
                            <text class="loop-sub" x="640" y="316" text-anchor="middle">histeresis</text>
                        </g>
                        <g class="loop-node" data-loop-node="heater">
                            <rect x="290" y="348" width="180" height="54" rx="12" />
                            <text class="loop-title" x="380" y="371" text-anchor="middle">Pemanas</text>
                            <text class="loop-sub" x="380" y="388" text-anchor="middle">heater 1 dan 2</text>
                        </g>
                        <g class="loop-node" data-loop-node="room">
                            <rect x="30" y="276" width="170" height="54" rx="12" />
                            <text class="loop-title" x="115" y="299" text-anchor="middle">Ruang</text>
                            <text class="loop-sub" x="115" y="316" text-anchor="middle">suhu berubah</text>
                        </g>
                        <g class="loop-node" data-loop-node="dht">
                            <rect x="30" y="96" width="170" height="54" rx="12" />
                            <text class="loop-title" x="115" y="119" text-anchor="middle">DHT22</text>
                            <text class="loop-sub" x="115" y="136" text-anchor="middle">umpan balik</text>
                        </g>

                        <text x="500" y="82" fill="#5d6a64" font-size="9">error</text>
                        <text x="650" y="264" fill="#5d6a64" font-size="9">output</text>
                        <text x="650" y="354" fill="#5d6a64" font-size="9">panas</text>
                        <text x="220" y="112" fill="#5d6a64" font-size="9">suhu ruang</text>
                    </svg>

                    <svg viewBox="0 0 340 510" class="mx-auto h-auto w-full max-w-[360px] md:hidden" role="img" aria-labelledby="loop-svg-title-mobile loop-svg-desc-mobile">
                        <title id="loop-svg-title-mobile">Diagram loop tertutup PID inkubator versi vertikal</title>
                        <desc id="loop-svg-desc-mobile">Setpoint turun ke error, PID, relay, pemanas, ruang, dan DHT22. Umpan balik dari DHT22 kembali ke error.</desc>
                        <defs>
                            <marker id="loop-arrow-mobile" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
                                <path d="M 0 0 L 10 5 L 0 10 z" fill="#7f8a84" />
                            </marker>
                        </defs>

                        <path class="loop-edge" data-loop-edge="setpoint" d="M 170 62 L 170 86" marker-end="url(#loop-arrow-mobile)" />
                        <path class="loop-edge" data-loop-edge="error" d="M 170 132 L 170 156" marker-end="url(#loop-arrow-mobile)" />
                        <path class="loop-edge" data-loop-edge="pid" d="M 170 202 L 170 226" marker-end="url(#loop-arrow-mobile)" />
                        <path class="loop-edge" data-loop-edge="relay" d="M 170 272 L 170 296" marker-end="url(#loop-arrow-mobile)" />
                        <path class="loop-edge" data-loop-edge="heater" d="M 170 342 L 170 366" marker-end="url(#loop-arrow-mobile)" />
                        <path class="loop-edge" data-loop-edge="feedback" d="M 170 412 L 170 436" marker-end="url(#loop-arrow-mobile)" />
                        <path class="loop-edge" data-loop-edge="feedback" d="M 80 459 L 40 459 L 40 109 L 80 109" marker-end="url(#loop-arrow-mobile)" />

                        <g class="loop-node" data-loop-node="setpoint">
                            <rect x="80" y="16" width="180" height="46" rx="12" />
                            <text class="loop-title" x="170" y="36" text-anchor="middle">Setpoint</text>
                            <text class="loop-sub" x="170" y="52" text-anchor="middle">target 33.5 C</text>
                        </g>
                        <g class="loop-node" data-loop-node="error">
                            <rect x="80" y="86" width="180" height="46" rx="12" />
                            <text class="loop-title" x="170" y="106" text-anchor="middle">Error</text>
                            <text class="loop-sub" x="170" y="122" text-anchor="middle">target - ruang</text>
                        </g>
                        <g class="loop-node" data-loop-node="pid">
                            <rect x="80" y="156" width="180" height="46" rx="12" />
                            <text class="loop-title" x="170" y="176" text-anchor="middle">PID</text>
                            <text class="loop-sub" x="170" y="192" text-anchor="middle">P + I + D</text>
                        </g>
                        <g class="loop-node" data-loop-node="relay">
                            <rect x="80" y="226" width="180" height="46" rx="12" />
                            <text class="loop-title" x="170" y="246" text-anchor="middle">Relay</text>
                            <text class="loop-sub" x="170" y="262" text-anchor="middle">histeresis</text>
                        </g>
                        <g class="loop-node" data-loop-node="heater">
                            <rect x="80" y="296" width="180" height="46" rx="12" />
                            <text class="loop-title" x="170" y="316" text-anchor="middle">Pemanas</text>
                            <text class="loop-sub" x="170" y="332" text-anchor="middle">heater 1 dan 2</text>
                        </g>
                        <g class="loop-node" data-loop-node="room">
                            <rect x="80" y="366" width="180" height="46" rx="12" />
                            <text class="loop-title" x="170" y="386" text-anchor="middle">Ruang</text>
                            <text class="loop-sub" x="170" y="402" text-anchor="middle">suhu berubah</text>
                        </g>
                        <g class="loop-node" data-loop-node="dht">
                            <rect x="80" y="436" width="180" height="46" rx="12" />
                            <text class="loop-title" x="170" y="456" text-anchor="middle">DHT22</text>
                            <text class="loop-sub" x="170" y="472" text-anchor="middle">umpan balik</text>
                        </g>
                    </svg>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line px-3 py-3 sm:px-5">
                        <p class="text-xs leading-relaxed text-ink-soft">Pilih tahap untuk melihat perannya.</p>
                        <button type="button" class="btn btn-secondary btn-sm" data-loop-play>Putar alur</button>
                    </div>
                </div>

                <div class="card p-5">
                    <div data-stage-panels>
                        @foreach ($loopStages as $index => $stage)
                            <div data-stage-panel="{{ $index }}" @if ($index !== 0) hidden @endif>
                                <p class="eyebrow">Tahap {{ $index + 1 }}</p>
                                <h3 class="mt-2 text-xl font-semibold">{{ $stage['label'] }}</h3>
                                <p class="mt-2 text-sm leading-relaxed text-ink-soft">{{ $stage['body'] }}</p>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-5 grid grid-cols-[repeat(auto-fit,minmax(9rem,1fr))] gap-2">
                        @foreach ($loopStages as $index => $stage)
                            <button type="button" class="loop-stage {{ $index === 0 ? 'is-current' : '' }}" data-loop-stage="{{ $index }}" aria-pressed="{{ $index === 0 ? 'true' : 'false' }}">
                                <span class="font-mono text-[0.65rem] opacity-70">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                                <span class="text-left text-xs font-semibold">{{ $stage['label'] }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="mt-5 flex items-start gap-3 rounded-2xl border border-warn/25 bg-warn-soft px-4 py-3 text-sm leading-relaxed text-warn">
                <x-icon name="alert" class="mt-0.5 size-4 shrink-0" />
                <p><strong class="font-semibold">Pengaman di luar loop.</strong> DS18B20 minimal 37.5 C atau DHT22 minimal 38.0 C memaksa kedua pemanas mati. Jika DHT22 gagal tiga kali, heater dan kipas juga dimatikan.</p>
            </div>
        </section>

        <section id="rangkaian" class="scroll-mt-24 border-t border-line pt-10 sm:pt-14" aria-labelledby="rangkaian-title">
            <div class="grid gap-4 border-t border-line pt-8 md:grid-cols-[10rem_minmax(0,1fr)]">
                <p class="eyebrow">02 · Rangkaian</p>
                <div>
                    <h2 id="rangkaian-title" class="max-w-3xl text-balance text-3xl font-semibold tracking-[-0.035em] sm:text-4xl">Satu papan, dua sensor, empat sakelar.</h2>
                    <p class="mt-3 max-w-2xl text-pretty text-sm leading-relaxed text-ink-soft sm:text-base">
                        ESP32 tidak menggerakkan pemanas langsung. Relay memisahkan arus kontrol dari arus beban. Sensor memberi angka, relay yang mengambil keputusan akhir.
                    </p>
                </div>
            </div>

            <div class="mt-8 grid gap-5 lg:grid-cols-[minmax(0,1.45fr)_minmax(260px,0.55fr)] lg:items-start">
                <div class="card overflow-hidden">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-4 py-3 sm:px-5">
                        <h3 class="text-sm font-semibold">Skema papan</h3>
                        <p class="text-xs text-ink-soft">Geser horizontal di layar kecil.</p>
                    </div>
                    <div class="overflow-x-auto p-3 sm:p-5" tabindex="0" role="region" aria-label="Skema rangkaian, dapat digeser horizontal">
                        <svg viewBox="0 0 760 440" class="h-auto min-w-[680px] w-full" role="img" aria-labelledby="board-svg-title board-svg-desc">
                            <title id="board-svg-title">Skema kabel ESP32 ke sensor dan relay</title>
                            <desc id="board-svg-desc">ESP32 terhubung ke DHT22 di GPIO 5, DS18B20 di GPIO 4, LCD I2C, relay empat kanal, dan broker MQTT lewat Wi-Fi.</desc>
                            <defs>
                                <marker id="board-arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
                                    <path d="M 0 0 L 10 5 L 0 10 z" fill="#5d6a64" />
                                </marker>
                            </defs>

                            <path class="schematic-wire schematic-wire-sensor" d="M 190 72 C 230 72, 240 150, 270 185" />
                            <path class="schematic-wire schematic-wire-sensor" d="M 190 212 C 230 212, 240 210, 270 215" />
                            <path class="schematic-wire schematic-wire-sensor" d="M 190 352 C 230 352, 240 250, 270 245" />
                            <path class="schematic-wire schematic-wire-sensor" d="M 190 377 C 230 377, 240 280, 270 275" />
                            <path class="schematic-wire schematic-wire-actuator" d="M 490 185 C 515 185, 520 140, 540 130" />
                            <path class="schematic-wire schematic-wire-actuator" d="M 490 215 C 515 215, 520 175, 540 165" />
                            <path class="schematic-wire schematic-wire-actuator" d="M 490 245 C 515 245, 520 210, 540 200" />
                            <path class="schematic-wire schematic-wire-actuator" d="M 490 275 C 515 275, 520 245, 540 235" />
                            <path class="schematic-wire schematic-wire-network" d="M 380 160 C 450 160, 500 80, 560 48" marker-end="url(#board-arrow)" />

                            <g class="schematic-node schematic-sensor">
                                <rect x="30" y="40" width="160" height="64" rx="12" />
                                <text class="node-title" x="110" y="65" text-anchor="middle">DHT22</text>
                                <text class="node-sub" x="110" y="82" text-anchor="middle">GPIO 5 · ruang + RH</text>
                            </g>
                            <g class="schematic-node schematic-sensor">
                                <rect x="30" y="180" width="160" height="64" rx="12" />
                                <text class="node-title" x="110" y="205" text-anchor="middle">DS18B20</text>
                                <text class="node-sub" x="110" y="222" text-anchor="middle">GPIO 4 · suhu bayi</text>
                            </g>
                            <g class="schematic-node schematic-sensor">
                                <rect x="30" y="320" width="160" height="64" rx="12" />
                                <text class="node-title" x="110" y="345" text-anchor="middle">LCD I2C</text>
                                <text class="node-sub" x="110" y="362" text-anchor="middle">0x27 · SDA/SCL</text>
                            </g>
                            <g class="schematic-node schematic-board">
                                <rect x="270" y="160" width="220" height="140" rx="16" />
                                <text class="node-title" x="380" y="215" text-anchor="middle">ESP32</text>
                                <text class="node-sub" x="380" y="235" text-anchor="middle">Core 1 · kontrol</text>
                                <text class="node-sub" x="380" y="251" text-anchor="middle">Core 0 · jaringan</text>
                                <text class="node-sub" x="285" y="189">5</text>
                                <text class="node-sub" x="285" y="219">4</text>
                                <text class="node-sub" x="285" y="249">21</text>
                                <text class="node-sub" x="285" y="279">22</text>
                                <text class="node-sub" x="475" y="189" text-anchor="end">14</text>
                                <text class="node-sub" x="475" y="219" text-anchor="end">26</text>
                                <text class="node-sub" x="475" y="249" text-anchor="end">27</text>
                                <text class="node-sub" x="475" y="279" text-anchor="end">25</text>
                            </g>
                            <g class="schematic-node schematic-actuator">
                                <rect x="540" y="100" width="190" height="200" rx="14" />
                                <text class="node-title" x="635" y="126" text-anchor="middle">Relay 4 channel</text>
                                <rect x="555" y="140" width="160" height="28" rx="6" />
                                <rect x="555" y="176" width="160" height="28" rx="6" />
                                <rect x="555" y="212" width="160" height="28" rx="6" />
                                <rect x="555" y="248" width="160" height="28" rx="6" />
                                <text class="node-sub" x="635" y="158" text-anchor="middle">kipas</text>
                                <text class="node-sub" x="635" y="194" text-anchor="middle">heater 1</text>
                                <text class="node-sub" x="635" y="230" text-anchor="middle">heater 2</text>
                                <text class="node-sub" x="635" y="266" text-anchor="middle">blue light</text>
                            </g>
                            <g class="schematic-node schematic-network">
                                <rect x="560" y="20" width="150" height="56" rx="12" />
                                <text class="node-title" x="635" y="43" text-anchor="middle">Wi-Fi</text>
                                <text class="node-sub" x="635" y="60" text-anchor="middle">MQTT TLS · 8883</text>
                            </g>
                        </svg>
                    </div>
                    <div class="flex flex-wrap gap-x-5 gap-y-2 border-t border-line px-4 py-3 text-xs text-ink-soft sm:px-5">
                        <span class="flex items-center gap-2"><span class="size-2.5 rounded-sm bg-accent"></span>Sensor</span>
                        <span class="flex items-center gap-2"><span class="size-2.5 rounded-sm bg-warn"></span>Aktuator</span>
                        <span class="flex items-center gap-2"><span class="size-2.5 rounded-sm border border-dashed border-ink-soft"></span>Jaringan</span>
                    </div>
                </div>

                <div class="card p-5">
                    <h3 class="text-sm font-semibold">Peta pin</h3>
                    <dl class="mt-3 divide-y divide-line">
                        @foreach ($pinMap as $pin)
                            <div class="grid grid-cols-1 gap-3 py-3 sm:grid-cols-[5.5rem_minmax(0,1fr)]">
                                <dt class="font-mono text-xs font-semibold text-ink-soft">{{ $pin['pin'] }}</dt>
                                <dd>
                                    <p class="text-sm font-semibold">{{ $pin['part'] }}</p>
                                    <p class="mt-0.5 text-xs leading-relaxed text-ink-soft">{{ $pin['role'] }}</p>
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                    <p class="mt-4 rounded-xl bg-bone px-3 py-2 text-xs leading-relaxed text-ink-soft">
                        Relay aktif-low: <code class="font-mono text-ink">LOW</code> berarti nyala. Kelembapan hanya ditampilkan, tidak masuk PID.
                    </p>
                </div>
            </div>
        </section>

        <section id="terminal" class="scroll-mt-24 pt-20 sm:pt-28" aria-labelledby="terminal-title">
            <div class="grid gap-4 border-t border-line pt-8 md:grid-cols-[10rem_minmax(0,1fr)]">
                <p class="eyebrow">03 · Terminal kode</p>
                <div>
                    <h2 id="terminal-title" class="max-w-3xl text-balance text-3xl font-semibold tracking-[-0.035em] sm:text-4xl">Terminal adalah kelas utama.</h2>
                    <p class="mt-3 max-w-2xl text-pretty text-sm leading-relaxed text-ink-soft sm:text-base">
                        Pilih satu topik. Kode tetap memakai nomor baris asli. Panel kanan menerangkan tipe data, fungsi, alur nilai, dan alasan teknisnya.
                    </p>
                </div>
            </div>

            <div class="mt-5">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line pb-3">
                    <p class="text-sm font-semibold">Lima topik, dari include sampai loop</p>
                    <p class="text-xs text-ink-soft">Pilih topik untuk membuka penjelasan di terminal.</p>
                </div>
                <div class="mt-2 divide-y divide-line">
                    @foreach ($quickStarts as $index => $quick)
                        <button type="button" class="flex min-h-16 w-full items-center gap-4 py-3 text-left transition-colors hover:text-accent" data-open-terminal data-step="{{ $quick['step'] }}">
                            <span class="w-8 shrink-0 font-mono text-xs text-ink-soft">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-semibold">{{ $quick['label'] }}</span>
                                <span class="mt-0.5 block font-mono text-xs text-ink-soft">{{ $quick['detail'] }}</span>
                            </span>
                            <span class="shrink-0 text-xs text-ink-soft">Buka kode</span>
                        </button>
                    @endforeach
                </div>
            </div>
        </section>

        <section id="pid" class="scroll-mt-24 border-t border-line pt-10 sm:pt-14" aria-labelledby="pid-title">
            <div class="grid gap-4 border-t border-line pt-8 md:grid-cols-[10rem_minmax(0,1fr)]">
                <p class="eyebrow">04 · PID</p>
                <div>
                    <h2 id="pid-title" class="max-w-3xl text-balance text-3xl font-semibold tracking-[-0.035em] sm:text-4xl">PID, dijelaskan lewat tiga pertanyaan.</h2>
                    <p class="mt-3 max-w-2xl text-pretty text-sm leading-relaxed text-ink-soft sm:text-base">
                        Ikuti hitungan dari suhu ruang 28.0 C menuju setpoint 33.5 C. Nilai gain dan aturan relay mengikuti firmware.
                    </p>
                </div>
            </div>

            <div class="mt-8 grid gap-5 lg:grid-cols-2">
                <div>
                    <ol class="mt-5 space-y-4">
                        <li class="border-b border-line pb-4">
                            <h3 class="text-sm font-semibold">1. Sampel pertama, suhu 28.0 C</h3>
                            <p class="mt-1 font-mono text-xs leading-relaxed text-ink-soft">error = 33.5 - 28.0 = +5.5<br>P = 40 x 5.5 = +220<br>I = 0, karena error lebih dari 1 C<br>D = 0, sampel pertama jadi acuan<br>output = clamp(220, -100, 100) = +100</p>
                            <p class="mt-2 text-sm leading-relaxed text-ink-soft">Output mencapai batas maksimum. Kedua heater menyala.</p>
                        </li>
                        <li class="border-b border-line pb-4">
                            <h3 class="text-sm font-semibold">2. Sampel kedua, suhu 29.0 C</h3>
                            <p class="mt-1 font-mono text-xs leading-relaxed text-ink-soft">error = +4.5; P = +180<br>filter D = 0.3 x (28.0 - 29.0) = -0.3<br>D = 40 x -0.3 = -12<br>output = clamp(180 - 12, -100, 100) = +100</p>
                            <p class="mt-2 text-sm leading-relaxed text-ink-soft">Suhu naik, jadi D mengurangi dorongan panas. P masih besar dan output tetap dibatasi ke +100.</p>
                        </li>
                        <li class="border-b border-line pb-4">
                            <h3 class="text-sm font-semibold">3. Sampel ketiga, suhu 32.0 C</h3>
                            <p class="mt-1 font-mono text-xs leading-relaxed text-ink-soft">error = +1.5; P = +60<br>filter D = -0.3 + 0.3 x (-3.0 + 0.3) = -1.11<br>D = 40 x -1.11 = -44.4<br>output = +60 - 44.4 = +15.6</p>
                            <p class="mt-2 text-sm leading-relaxed text-ink-soft">Heater 2 mati pada output 15.6. Heater 1 tetap menyala sampai output turun ke 10, karena relay memakai histeresis 20/10.</p>
                        </li>
                        <li>
                            <h3 class="text-sm font-semibold">4. Sampel keempat, suhu 33.0 C</h3>
                            <p class="mt-1 font-mono text-xs leading-relaxed text-ink-soft">error = +0.5; P = +20<br>integral = 0.5; I = 0.5 x 0.5 = +0.25<br>filter D = -1.11 + 0.3 x (-1.0 + 1.11) = -1.077<br>D = 40 x -1.077 = -43.08<br>output = 20 + 0.25 - 43.08 = -22.83</p>
                            <p class="mt-2 text-sm leading-relaxed text-ink-soft">Error sudah di bawah 1 C, jadi I mulai menumpuk. D masih kuat karena suhu naik cepat; kipas menyala saat output mencapai -20.</p>
                        </li>
                    </ol>

                    <div class="mt-5 rounded-xl border border-line bg-bone p-4 font-mono text-xs leading-relaxed">
                        outputPid = clamp(kp x error + ki x integral + kd x turunan, -100, 100)
                    </div>
                    <p class="mt-4 text-sm leading-relaxed text-ink-soft">P bereaksi pada error sekarang. I mengoreksi error kecil yang menetap. D mengerem saat suhu naik cepat. Relay mempertahankan state di antara ambang nyala dan mati.</p>
                </div>

                <div class="card p-5" data-pid-sim>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-semibold">Simulator PID</h3>
                            <p class="mt-1 text-xs leading-relaxed text-ink-soft">Setpoint 33.5 C, kp 40, ki 0.5, kd 40. Ini alat belajar, bukan pengganti tuning di alat asli.</p>
                        </div>
                    </div>

                    <div class="mt-5">
                        <label class="field-label" for="room-slider">Suhu ruang</label>
                        <input id="room-slider" type="range" min="28" max="38" step="0.1" value="32.4" class="mt-2 h-11 w-full accent-accent" />
                        <p class="mt-1 text-xs text-ink-soft">Geser untuk melihat P, I, D, dan keputusan relay berubah.</p>
                    </div>

                    <dl class="mt-5 grid grid-cols-2 gap-x-4 gap-y-2 text-xs">
                        <dt class="text-ink-soft">Suhu ruang</dt>
                        <dd class="text-right font-mono font-semibold" data-pid-room>32.4 C</dd>
                        <dt class="text-ink-soft">Error</dt>
                        <dd class="text-right font-mono font-semibold" data-pid-error>+1.1 C</dd>
                        <dt class="text-ink-soft">P</dt>
                        <dd class="text-right font-mono font-semibold" data-pid-p>+44.0</dd>
                        <dt class="text-ink-soft">I</dt>
                        <dd class="text-right font-mono font-semibold" data-pid-i>+0.0</dd>
                        <dt class="text-ink-soft">D</dt>
                        <dd class="text-right font-mono font-semibold" data-pid-d>+0.0</dd>
                        <dt class="text-ink-soft">Output</dt>
                        <dd class="text-right font-mono font-semibold" data-pid-out aria-live="polite">+44.0</dd>
                    </dl>

                    <div class="mt-5 space-y-3">
                        <div>
                            <div class="flex justify-between text-[0.68rem] font-semibold text-ink-soft"><span>P</span><span data-bar-value="p">+44.0</span></div>
                            <div class="pid-bar mt-1"><div class="pid-fill" data-bar="p"></div></div>
                        </div>
                        <div>
                            <div class="flex justify-between text-[0.68rem] font-semibold text-ink-soft"><span>I</span><span data-bar-value="i">+0.0</span></div>
                            <div class="pid-bar mt-1"><div class="pid-fill" data-bar="i"></div></div>
                        </div>
                        <div>
                            <div class="flex justify-between text-[0.68rem] font-semibold text-ink-soft"><span>D</span><span data-bar-value="d">+0.0</span></div>
                            <div class="pid-bar mt-1"><div class="pid-fill" data-bar="d"></div></div>
                        </div>
                    </div>

                    <dl class="mt-5 grid grid-cols-[repeat(auto-fit,minmax(6rem,1fr))] gap-2 text-center text-[0.68rem] font-semibold">
                        <div class="rounded-xl border border-line px-2 py-2">
                            <dt class="text-ink-soft">Heater 1</dt>
                            <dd class="mt-1 text-accent" data-relay="heater1">ON</dd>
                        </div>
                        <div class="rounded-xl border border-line px-2 py-2">
                            <dt class="text-ink-soft">Heater 2</dt>
                            <dd class="mt-1 text-ink-soft" data-relay="heater2">OFF</dd>
                        </div>
                        <div class="rounded-xl border border-line px-2 py-2">
                            <dt class="text-ink-soft">Kipas</dt>
                            <dd class="mt-1 text-ink-soft" data-relay="fan">OFF</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </section>

        <section id="pustaka" class="scroll-mt-24 border-t border-line pt-10 sm:pt-14" aria-labelledby="pustaka-title">
            <div class="grid gap-4 border-t border-line pt-8 md:grid-cols-[10rem_minmax(0,1fr)]">
                <p class="eyebrow">05 · Pustaka</p>
                <div>
                    <h2 id="pustaka-title" class="max-w-3xl text-balance text-3xl font-semibold tracking-[-0.035em] sm:text-4xl">Fungsi yang tidak ditulis sendiri.</h2>
                    <p class="mt-3 max-w-2xl text-pretty text-sm leading-relaxed text-ink-soft sm:text-base">
                        Library menyediakan protokol dan driver. Daftar ini menyebut fungsi yang benar-benar dipakai firmware, bukan seluruh isi library.
                    </p>
                </div>
            </div>

            <div class="mt-8 grid gap-3 sm:grid-cols-2">
                @foreach ($libraries as $library)
                    <details class="group rounded-xl border border-line bg-paper px-4 py-1 open:bg-bone">
                        <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between gap-3 py-2 text-sm font-semibold">
                            <span>
                                {{ $library['name'] }}
                                <span class="mt-0.5 block text-xs font-normal text-ink-soft">{{ $library['role'] }}</span>
                            </span>
                            <span class="shrink-0 text-xs text-ink-soft group-open:hidden">Buka</span>
                            <span class="hidden shrink-0 text-xs text-ink-soft group-open:inline">Tutup</span>
                        </summary>
                        <ul class="mb-3 mt-1 space-y-1 break-words border-t border-line pt-3 font-mono text-xs leading-relaxed text-ink-soft">
                            @foreach ($library['functions'] as $function)
                                <li>{{ $function }}</li>
                            @endforeach
                        </ul>
                    </details>
                @endforeach
            </div>

            <p class="mt-5 text-xs leading-relaxed text-ink-soft">Daftar ini berasal dari include di baris 1 sampai 12. FreeRTOS tersedia lewat Arduino.h, bukan include terpisah. Nama fungsi mengikuti versi library yang dipakai firmware.</p>
        </section>

        <section class="mt-20 border-t border-line pt-6" aria-label="Sumber perilaku">
            <p class="text-xs leading-relaxed text-ink-soft">
                Sumber perilaku: <code class="break-all font-mono text-ink">firmware/Incubator/Incubator.ino</code>. README dan PRD tidak dipakai sebagai acuan karena sebagian sudah tertinggal.
            </p>
        </section>
    </div>

    <dialog id="code-terminal" class="term-dialog" aria-labelledby="terminal-dialog-title">
        <div class="term-shell">
            <header class="term-top">
                <div class="min-w-0">
                    <p class="term-kicker">Terminal kode · Incubator.ino</p>
                    <h2 id="terminal-dialog-title" class="truncate text-base font-semibold text-white sm:text-lg">Baca satu baris, lalu lanjut.</h2>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <label class="sr-only" for="terminal-step">Pilih langkah</label>
                    <select id="terminal-step" class="term-select" data-term-step>
                        @foreach ($chunks as $index => $chunk)
                            <option value="{{ $index }}">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }} · {{ $chunk['label'] }}</option>
                        @endforeach
                    </select>
                    <button type="button" class="term-btn term-btn-nav" data-term-prev aria-label="Langkah sebelumnya"><span class="hidden sm:inline">Sebelumnya</span><span class="sm:hidden" aria-hidden="true">&larr;</span></button>
                    <button type="button" class="term-btn term-btn-nav" data-term-next aria-label="Langkah berikutnya"><span class="hidden sm:inline">Berikutnya</span><span class="sm:hidden" aria-hidden="true">&rarr;</span></button>
                    <form method="dialog">
                        <button class="term-btn term-btn-close" value="close">Tutup</button>
                    </form>
                </div>
            </header>

            <div class="term-body">
                <section class="term-code" aria-label="Kode sumber Incubator.ino">
                    <div class="term-code-head">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="term-kicker" data-term-chunk-label></p>
                            <span class="font-mono text-[0.65rem] text-[#7f978d]" data-term-counter></span>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <div class="flex flex-wrap items-center gap-2" data-term-line-nav>
                                <button type="button" class="term-btn" data-term-line-prev>Baris -</button>
                                <span class="font-mono text-[0.68rem] text-[#9eb5ab]" data-term-line-counter></span>
                                <button type="button" class="term-btn" data-term-line-next>Baris +</button>
                            </div>
                            <button type="button" class="term-btn" data-term-mode-toggle aria-pressed="true">Ringkas</button>
                            <button type="button" class="term-btn" data-term-source-toggle aria-pressed="false">Source penuh</button>
                        </div>
                    </div>

                    <div class="term-scroll" data-term-chunk-view>
                        <pre class="term-code-surface"><code data-term-lines></code></pre>
                    </div>

                    <div class="term-scroll" data-term-full hidden>
                        <pre class="term-code-surface"><code>@foreach ($lines as $number => $line)
<span class="term-row is-static"><span class="term-num">{{ $number + 1 }}</span><span class="term-text">{!! highlightInoLine($line) !!}</span></span>@endforeach</code></pre>
                    </div>
                </section>

                <aside class="term-explain" aria-labelledby="terminal-explain-title">
                    <div class="term-scroll p-4 sm:p-5">
                        <p class="term-kicker" data-term-line-badge></p>
                        <h3 id="terminal-explain-title" class="mt-1 text-lg font-semibold text-white" data-term-code-heading></h3>
                        <p class="mt-1 font-mono text-xs text-[#9eb5ab]" data-term-line-title></p>
                        <p class="mt-3 text-sm leading-relaxed text-[#c5d6ce]" data-term-line-note aria-live="polite"></p>
                        <div class="mt-5 rounded-xl border border-[#6fd1ae]/25 bg-[#6fd1ae]/10 px-4 py-3 text-xs leading-relaxed text-[#b9e8d6]" data-term-chunk-effect></div>
                        <div class="mt-6 border-t border-white/10 pt-4">
                            <p class="term-kicker">Konteks langkah</p>
                            <p class="mt-2 text-sm leading-relaxed text-[#c5d6ce]" data-term-chunk-body></p>
                        </div>
                        <div class="mt-6 border-t border-white/10 pt-4" data-term-library-block hidden>
                            <p class="term-kicker">Pustaka di langkah ini</p>
                            <ul class="mt-3 space-y-3" data-term-libraries></ul>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </dialog>
@endsection

@section('scripts')
    <script>
        (() => {
            const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
            const chunks = @json($chunks);
            const libraries = @json($libraries);
            const highlighted = @json($highlightLines);
            const loopStages = @json($loopStages);
            const libraryByName = Object.fromEntries(libraries.map((library) => [library.name, library]));

            const loopNodes = [...document.querySelectorAll('[data-loop-node]')];
            const loopEdges = [...document.querySelectorAll('[data-loop-edge]')];
            const loopButtons = [...document.querySelectorAll('[data-loop-stage]')];
            const loopPanels = [...document.querySelectorAll('[data-stage-panel]')];
            const loopPlay = document.querySelector('[data-loop-play]');
            let loopIndex = 0;
            let loopTimer = null;

            function renderLoopStage(index) {
                loopIndex = index;
                const stage = loopStages[index];

                loopNodes.forEach((node) => {
                    node.classList.toggle('is-active', stage.nodes.includes(node.dataset.loopNode));
                });

                loopEdges.forEach((edge) => {
                    edge.classList.toggle('is-active', stage.edges.includes(edge.dataset.loopEdge));
                });

                loopButtons.forEach((button, buttonIndex) => {
                    const current = buttonIndex === index;
                    button.classList.toggle('is-current', current);
                    button.setAttribute('aria-pressed', String(current));
                });

                loopPanels.forEach((panel, panelIndex) => {
                    panel.hidden = panelIndex !== index;
                });
            }

            function stopLoop() {
                window.clearInterval(loopTimer);
                loopTimer = null;
                loopPlay.textContent = 'Putar ulang';
            }

            loopButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    window.clearInterval(loopTimer);
                    loopTimer = null;
                    loopPlay.textContent = 'Putar alur';
                    renderLoopStage(Number(button.dataset.loopStage));
                });
            });

            if (reduceMotion.matches) {
                loopPlay.hidden = true;
            } else {
                loopPlay.addEventListener('click', () => {
                    if (loopTimer !== null) {
                        stopLoop();
                        return;
                    }

                    renderLoopStage(0);
                    loopPlay.textContent = 'Jeda alur';
                    loopTimer = window.setInterval(() => {
                        if (loopIndex >= loopStages.length - 1) {
                            stopLoop();
                            return;
                        }

                        renderLoopStage(loopIndex + 1);

                        if (loopIndex >= loopStages.length - 1) {
                            stopLoop();
                        }
                    }, 1100);
                });
            }

            const dialog = document.getElementById('code-terminal');
            const termStep = dialog.querySelector('[data-term-step]');
            const termChunkLabel = dialog.querySelector('[data-term-chunk-label]');
            const termCounter = dialog.querySelector('[data-term-counter]');
            const termLines = dialog.querySelector('[data-term-lines]');
            const termChunkView = dialog.querySelector('[data-term-chunk-view]');
            const termFull = dialog.querySelector('[data-term-full]');
            const termLineNav = dialog.querySelector('[data-term-line-nav]');
            const termLineCounter = dialog.querySelector('[data-term-line-counter]');
            const termLineBadge = dialog.querySelector('[data-term-line-badge]');
            const termLineTitle = dialog.querySelector('[data-term-line-title]');
            const termCodeHeading = dialog.querySelector('[data-term-code-heading]');
            const termLineNote = dialog.querySelector('[data-term-line-note]');
            const termChunkEffect = dialog.querySelector('[data-term-chunk-effect]');
            const termChunkBody = dialog.querySelector('[data-term-chunk-body]');
            const termLibraryBlock = dialog.querySelector('[data-term-library-block]');
            const termLibraries = dialog.querySelector('[data-term-libraries]');
            const termModeToggle = dialog.querySelector('[data-term-mode-toggle]');
            const sourceToggle = dialog.querySelector('[data-term-source-toggle]');

            let termChunk = 0;
            let termLine = 0;
            let sourceOpen = false;
            let technicalMode = true;

            function renderTerminal() {
                const chunk = chunks[termChunk];
                const [lineNumber, lineNote] = chunk.lines[termLine];

                termStep.value = String(termChunk);
                termChunkLabel.textContent = chunk.label;
                termCounter.textContent = (termChunk + 1) + '/' + chunks.length;
                termLineCounter.textContent = (termLine + 1) + '/' + chunk.lines.length;
                termLineBadge.textContent = chunk.label + ' · baris ' + lineNumber;
                termLineTitle.textContent = 'Baris ' + lineNumber + ' · ' + (lineNote.length > 58 ? lineNote.slice(0, 55) + '...' : lineNote);
                termLineNote.textContent = lineNote;
                termChunkEffect.textContent = chunk.effect;
                termChunkBody.textContent = technicalMode ? chunk.body : lineNote;
                termCodeHeading.textContent = technicalMode ? 'Penjelasan teknis' : 'Penjelasan singkat';
                termModeToggle.setAttribute('aria-pressed', String(technicalMode));
                termModeToggle.textContent = technicalMode ? 'Ringkas' : 'Teknis';
                termLibraries.replaceChildren();

                chunk.libraries.forEach((name) => {
                    const library = libraryByName[name];
                    if (!library) return;

                    const item = document.createElement('li');
                    const title = document.createElement('p');
                    const role = document.createElement('p');
                    const functions = document.createElement('p');
                    title.className = 'text-xs font-semibold text-white';
                    title.textContent = library.name;
                    role.className = 'mt-0.5 text-xs text-[#c5d6ce]';
                    role.textContent = library.role;
                    functions.className = 'mt-1 break-words font-mono text-xs leading-relaxed text-[#b9e8d6]';
                    functions.textContent = library.functions.join(' · ');
                    item.append(title, role, functions);
                    termLibraries.append(item);
                });

                termLibraryBlock.hidden = termLibraries.childElementCount === 0;

                termLines.replaceChildren();
                chunk.lines.forEach(([number], index) => {
                    const row = document.createElement('button');
                    row.type = 'button';
                    row.className = 'term-row' + (index === termLine ? ' is-active' : '');
                    row.setAttribute('aria-current', index === termLine ? 'true' : 'false');
                    row.innerHTML = '<span class="term-num">' + number + '</span><span class="term-text">' + (highlighted[number] ?? '') + '</span>';
                    row.addEventListener('click', () => {
                        termLine = index;
                        renderTerminal();
                    });
                    termLines.append(row);
                });
            }

            function setSourceOpen(open) {
                sourceOpen = open;
                termChunkView.hidden = open;
                termFull.hidden = !open;
                termLineNav.hidden = open;
                sourceToggle.setAttribute('aria-pressed', String(open));
                sourceToggle.textContent = open ? 'Kembali ke langkah' : 'Source penuh';
            }

            function chooseChunk(index) {
                termChunk = (index + chunks.length) % chunks.length;
                termLine = 0;
                if (sourceOpen) setSourceOpen(false);
                renderTerminal();
            }

            function chooseLine(index) {
                const total = chunks[termChunk].lines.length;
                termLine = (index + total) % total;
                renderTerminal();
            }

            document.querySelectorAll('[data-open-terminal]').forEach((button) => {
                button.addEventListener('click', () => {
                    chooseChunk(Number(button.dataset.step || 0));
                    if (!dialog.open) dialog.showModal();
                });
            });

            termStep.addEventListener('change', () => chooseChunk(Number(termStep.value)));
            termModeToggle.addEventListener('click', () => {
                technicalMode = !technicalMode;
                renderTerminal();
            });
            dialog.querySelector('[data-term-prev]').addEventListener('click', () => chooseChunk(termChunk - 1));
            dialog.querySelector('[data-term-next]').addEventListener('click', () => chooseChunk(termChunk + 1));
            dialog.querySelector('[data-term-line-prev]').addEventListener('click', () => chooseLine(termLine - 1));
            dialog.querySelector('[data-term-line-next]').addEventListener('click', () => chooseLine(termLine + 1));
            sourceToggle.addEventListener('click', () => setSourceOpen(!sourceOpen));

            const input = document.getElementById('room-slider');
            const targetTemp = 33.5;
            const kp = 40;
            const ki = 0.5;
            const kd = 40;
            let previousRoom = Number(input.value);
            let filteredDerivative = 0;
            let integral = 0;

            const clamp = (value, low, high) => Math.min(high, Math.max(low, value));
            const signed = (value) => (value >= 0 ? '+' : '') + value.toFixed(1);

            function updatePid() {
                const room = Number(input.value);
                const error = targetTemp - room;

                if (Math.abs(error) < 1) {
                    integral = clamp(integral + error, -15 / ki, 15 / ki);
                }

                filteredDerivative += 0.3 * ((previousRoom - room) - filteredDerivative);
                previousRoom = room;

                const p = kp * error;
                const i = ki * integral;
                const d = kd * filteredDerivative;
                const output = clamp(p + i + d, -100, 100);

                document.querySelector('[data-pid-room]').textContent = room.toFixed(1) + ' C';
                document.querySelector('[data-pid-error]').textContent = signed(error) + ' C';
                document.querySelector('[data-pid-p]').textContent = signed(p);
                document.querySelector('[data-pid-i]').textContent = signed(i);
                document.querySelector('[data-pid-d]').textContent = signed(d);
                document.querySelector('[data-pid-out]').textContent = signed(output);

                [['p', p], ['i', i], ['d', d]].forEach(([key, value]) => {
                    const bar = document.querySelector('[data-bar="' + key + '"]');
                    bar.style.transform = 'scaleX(' + Math.min(1, Math.abs(value) / 100) + ')';
                    bar.classList.toggle('is-negative', value < 0);
                    document.querySelector('[data-bar-value="' + key + '"]').textContent = signed(value);
                });

                const heater1 = output >= 20;
                const heater2 = output >= 60;
                const fan = output <= -20;

                [['heater1', heater1], ['heater2', heater2], ['fan', fan]].forEach(([key, value]) => {
                    const element = document.querySelector('[data-relay="' + key + '"]');
                    element.textContent = value ? 'ON' : 'OFF';
                    element.className = value ? 'mt-1 text-accent' : 'mt-1 text-ink-soft';
                });
            }

            input.addEventListener('input', updatePid);
            renderLoopStage(0);
            renderTerminal();
            updatePid();
        })();
    </script>
@endsection
