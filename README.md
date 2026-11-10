# Inkubator Bayi Otomatis Berbasis IoT
 
Inkubator bayi yang menjaga suhu secara otomatis: DHT22 membaca suhu, ESP32 mengatur 4 pemanas lewat relay sesuai berat dan umur bayi, dan datanya dipantau lewat IoT.
 
## Anggota Kelompok
 
| Nama | NIM |
|---|---|
| Abyan Devadi | H1H024049 |
| Muhammmad Faiz Mubarok | H1H024051 |
| Hafish Athallah | H1H024052 |
 
## Backend (Laravel + Sanctum)

Backend only. No view code. All rules live in `config/incubator.php` and `app/Support/IncubatorRules.php`.

### Run

```sh
composer install
cp .env.example .env          # then set ESP_API_KEY
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve
```

Seed creates one petugas: `petugas@example.com` / `password`. Change it after first login.

### Endpoints

| Method | Path | Guard |
|---|---|---|
| POST | `/api/login` | none |
| POST | `/api/logout` | sanctum |
| GET | `/api/profile` | sanctum |
| GET/POST | `/api/babies` | sanctum |
| GET/PUT/DELETE | `/api/babies/{id}` | sanctum |
| POST | `/api/babies/{id}/activate` | sanctum |
| POST | `/api/babies/{id}/deactivate` | sanctum |
| GET | `/api/babies/{id}/readings` | sanctum |
| GET | `/api/babies/{id}/readings/latest` | sanctum |
| POST | `/api/readings` | `X-Api-Key` |

`GET /api/babies/{id}/readings` accepts `from`, `to` (date) and `limit` (1-500, default 100).

### ESP32 request

```sh
curl -X POST http://localhost:8000/api/readings \
  -H 'X-Api-Key: your-key' -H 'Content-Type: application/json' \
  -d '{"skin_temperature":36.5,"incubator_temperature":33.5,"humidity":55}'
```

Reply:

```json
{"setpoint":34.7,"heaters":4,"buzzer":false,"humidity_ok":true}
```

No active baby: `{"setpoint":null,"heaters":0,"buzzer":false}` and nothing is stored.

### Assumptions

- Age counts from admission to the incubator, not birth. An age between rows uses the previous row; beyond 12 days keeps the last row.
- Premature baby above 2500 g uses the 1501-2500 g column. Otherwise prematurity is only recorded. Change `premature_weight_ceiling_grams` to alter this.
- Heaters rise at once and drop only after the gap passes the boundary plus `hysteresis` (0.1 °C). The previous count comes from the last reading.
- Humidity outside 50-60 % is flagged with `humidity_ok`, never alarmed. Skin temperature is stored only.
- The table itself lives in `temperature_profiles` (12 rows, `TemperatureProfileSeeder`), so setpoints are editable without touching code.
