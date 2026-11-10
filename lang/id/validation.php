<?php

// Only the rules the dashboard uses; missing keys fall back to English.
return [
    'required' => 'Kolom :attribute wajib diisi.',
    'string' => 'Kolom :attribute harus berupa teks.',
    'integer' => 'Kolom :attribute harus berupa angka bulat.',
    'email' => 'Kolom :attribute harus berupa alamat email yang valid.',
    'boolean' => 'Kolom :attribute harus bernilai ya atau tidak.',
    'date' => 'Kolom :attribute harus berupa tanggal yang valid.',
    'after_or_equal' => 'Kolom :attribute harus setelah atau sama dengan :date.',
    'exists' => ':attribute yang dipilih tidak valid.',

    'min' => [
        'numeric' => 'Kolom :attribute minimal :min.',
        'string' => 'Kolom :attribute minimal :min karakter.',
    ],
    'max' => [
        'numeric' => 'Kolom :attribute maksimal :max.',
        'string' => 'Kolom :attribute maksimal :max karakter.',
    ],

    'attributes' => [
        'name' => 'nama bayi',
        'weight_grams' => 'berat',
        'premature' => 'prematur',
        'email' => 'email',
        'password' => 'kata sandi',
        'from' => 'waktu awal',
        'to' => 'waktu akhir',
        'babyId' => 'bayi',
    ],
];
