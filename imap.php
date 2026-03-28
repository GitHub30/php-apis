<?php
header('X-Accel-Buffering: no');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: *');
header('Access-Control-Allow-Headers: *');

$user = $_GET['user'] ?? '';
$password = $_GET['password'] ?? '';
$host = $_GET['host'] ?? '';

if ($user === '' || $password === '' || $host === '') {
    http_response_code(400);
    exit;
}

$mbox = imap_open("{{$host}:993/imap/ssl}INBOX", $user, $password);

if (!$mbox) {
    http_response_code(401);
    echo imap_last_error();
    exit;
}

$emails = [];
$numMessages = imap_num_msg($mbox);

for ($i = $numMessages; $i >= max(1, $numMessages - 49); $i--) {
    $header = imap_headerinfo($mbox, $i);
    $overview = imap_fetch_overview($mbox, $i, 0);
    $structure = imap_fetchstructure($mbox, $i);

    $encoding = $structure->encoding ?? 0;
    $body = imap_fetchbody($mbox, $i, '1');

    switch ($encoding) {
        case 3: // BASE64
            $body = base64_decode($body);
            break;
        case 4: // QUOTED-PRINTABLE
            $body = quoted_printable_decode($body);
            break;
    }

    $from = '';
    if (isset($header->from[0])) {
        $f = $header->from[0];
        $from = isset($f->personal) ? imap_utf8($f->personal) . ' <' . $f->mailbox . '@' . $f->host . '>' : $f->mailbox . '@' . $f->host;
    }

    $to = '';
    if (isset($header->to[0])) {
        $t = $header->to[0];
        $to = isset($t->personal) ? imap_utf8($t->personal) . ' <' . $t->mailbox . '@' . $t->host . '>' : $t->mailbox . '@' . $t->host;
    }

    $emails[] = [
        'id' => $i,
        'subject' => isset($overview[0]->subject) ? imap_utf8($overview[0]->subject) : '',
        'from' => $from,
        'to' => $to,
        'date' => $header->date ?? '',
        'seen' => isset($overview[0]->seen) && $overview[0]->seen === 1,
        'body' => mb_convert_encoding($body, 'UTF-8', 'auto'),
    ];
}

imap_close($mbox);

header('Content-Type: application/json');
echo json_encode(['count' => $numMessages, 'emails' => $emails], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
