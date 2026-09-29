<?php

return [

    /*
    | Kayıt anahtarı artık her kurumun kendi kaydında durur.
    | İlk kuruma taşıma için eski env değeri kullanılır.
    */
    'enrollment_key' => env('BOARD_ENROLLMENT_KEY', ''),

    'first_org_name' => env('FIRST_ORG_NAME', 'İlk kurum'),

    'first_org_code' => env('FIRST_ORG_CODE', '00000000'),

    /*
    | Kilit ekranındaki karekod bu süre dolunca geçersiz olur.
    | Yeni kod gelince eskisi hemen düşer.
    */
    'qr_ttl_seconds' => (int) env('BOARD_QR_TTL', 25),

    /*
    | Bu süre boyunca bildirim gelmezse tahta listede çevrimdışı görünür.
    */
    'offline_seconds' => (int) env('BOARD_OFFLINE_SECONDS', 45),

    /*
    | Tahta komut bağlantısını bu kadar saniye açık tutar.
    | Komut bu yoklamayla iner; tahtaya dışarıdan kapı açılmaz.
    */
    'command_wait_seconds' => (int) env('BOARD_COMMAND_WAIT', 20),

    /*
    | İletilmiş ama onaylanmamış komut bu süre sonra yeniden verilir.
    */
    'command_retry_seconds' => (int) env('BOARD_COMMAND_RETRY', 25),

    /*
    | Açma izni, okutmanın hemen ardından alınmalıdır.
    | Kilitleme ve kapatma, bağlantı dönene kadar bekler.
    */
    'unlock_ttl_seconds' => (int) env('BOARD_UNLOCK_TTL', 90),

    /*
    | Tahtaya iletilip onaylanmayan kapatma bu süre sonra düşer; tahta açılınca yeniden kapanmaz.
    */
    'shutdown_ttl_seconds' => (int) env('BOARD_SHUTDOWN_TTL', 600),

    /*
    | Yoklama günü ve saatleri okulun saat dilimine göre hesaplanır.
    */
    'school_timezone' => env('SCHOOL_TIMEZONE', 'Europe/Istanbul'),

    'attendance_lessons' => (int) env('ATTENDANCE_LESSONS', 8),

];
