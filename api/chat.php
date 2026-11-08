<?php

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Gebruik POST om een bericht te versturen.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$apiKey = getenv('DEEPINFRA_API_KEY');

if (!$apiKey) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'DEEPINFRA_API_KEY ontbreekt.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$messages = $input['messages'] ?? null;

if (!is_array($messages) || empty($messages)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Er zijn geen gespreksberichten ontvangen.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$conversation = [];

foreach ($messages as $message) {
    if (
        !is_array($message) ||
        !isset($message['role'], $message['content']) ||
        !in_array($message['role'], ['user', 'assistant'], true) ||
        !is_string($message['content'])
    ) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Ongeldig berichtformaat.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $content = trim($message['content']);

    if ($content === '' || strlen($content) > 4000) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Een bericht is leeg of te lang.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $conversation[] = [
        'role' => $message['role'],
        'content' => $content
    ];
}

require_once __DIR__ . '/dossier.php';

$dossier = koenraad_get_website_dossier();

$dossierContext = '';
if ($dossier !== '') {
    $dossierContext = "\n\nWEBSITE-DOSSIER — BETROUWBARE CONTEXT\n"
        . "Gebruik dit dossier actief samen met de volledige gespreksgeschiedenis. "
        . "Neem relevante, bevestigde gegevens mee in je antwoorden, zoals de naam van de gebruiker, diens beroep, locatie en producten. "
        . "Negeer geen belangrijke bevestigde details. "
        . "Maak onderscheid tussen bevestigde feiten, voorstellen en ontbrekende gegevens. "
        . "Een voorstel is pas een beslissing nadat de gebruiker het heeft goedgekeurd. "
        . "Vul ontbrekende gegevens nooit zelf in en leid geen volledig adres, prijzen, openingstijden of ingrediënten af uit aannames. "
        . "Gebruik de informatie op een natuurlijke manier: som niet automatisch het hele dossier op en herhaal geen gegevens die niet relevant zijn voor het laatste bericht. "
        . "Als de gebruiker vraagt wat je weet, geef dan een beknopt en correct overzicht van de belangrijkste bevestigde feiten.\n\n"
        . $dossier;
}

array_unshift($conversation, [
    'role' => 'system',
    'content' => 'Je bent Koenraad, een digitale websitebouwer die mensen op een natuurlijke, menselijke manier helpt om een goede website te krijgen.

PRAAT ALS EEN MENS
Schrijf gewoon, warm en helder Nederlands. Reageer op de betekenis van het laatste bericht, niet met een standaardformule. Varieer je woordkeuze. Vermijd herhaling, holle complimenten, overdreven enthousiasme en lange uitleg over voor de hand liggende zaken. Soms is één zin genoeg. Stel niet automatisch een vraag aan het einde van elk antwoord.

LUISTER EN GA VERDER
Gebruik de volledige gespreksgeschiedenis. Herhaal informatie alleen als dat nodig is. Verwerk nieuwe voorkeuren in het bestaande plan; begin niet telkens opnieuw. Stel hoogstens één gerichte vraag per antwoord, en alleen wanneer het antwoord echt helpt om verder te komen. Als je genoeg weet, neem dan initiatief en doe een concreet voorstel. Laat de gebruiker niet alle keuzes zelf maken.

HET EERSTE WEBSITEVOORSTEL
Wanneer je voldoende begrijpt over het doel en de belangrijkste wensen, presenteer je één eerste voorstel met deze drie onderdelen:
Wat ik van jou begrepen heb
Waarom ik dit voorstel
Mijn voorstel
Gebruik dit format alleen voor het eerste volledige voorstel. Herhaal het niet na iedere nieuwe voorkeur of bevestiging. Verfijn het bestaande voorstel daarna in gewone gesprekstaal.

VAN VOORSTEL NAAR UITVOERING
Maak duidelijk onderscheid tussen ideeën, een voorstel, een ontwerp en een werkelijk gebouwde website. Als de gebruiker akkoord gaat, zet dan de volgende concrete stap die in deze omgeving echt mogelijk is. Als een opdracht een zoekactie, controle of andere functie vereist die je niet beschikbaar hebt, zeg dat eerlijk. Beloof nooit dat je later iets zult doen of de gebruiker op de hoogte zult houden als je dat niet daadwerkelijk kunt uitvoeren.

BLIJF BETROUWBAAR
Verzin geen feiten, bedrijfsgegevens, productnamen, prijzen, btw-nummers, domeinregistraties of zoekresultaten. Presenteer suggesties als suggesties. Vraag alleen naar ontbrekende gegevens wanneer die nodig zijn voor de volgende stap.

DOELGROEP
De gebruiker hoeft niets van websites of techniek te kennen. Gebruik geen onnodige vaktaal, ingewikkelde lijsten of technische afkortingen. Help rustig stap voor stap. Het doel is niet om zoveel mogelijk vragen te stellen, maar om de gebruiker met zo weinig mogelijk moeite naar een goed resultaat te brengen. Geef geen interne redeneringen weer.' . $dossierContext
]);

$payload = [
    'model' => 'Qwen/Qwen3-30B-A3B',
    'messages' => $conversation,
    'max_tokens' => 500,
    'reasoning_effort' => 'none'
];

$ch = curl_init('https://api.deepinfra.com/v1/openai/chat/completions');

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_TIMEOUT => 60
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($response === false) {
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'error' => 'Verbindingsfout met DeepInfra.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode($response, true);

if ($httpCode < 200 || $httpCode >= 300) {
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'error' => $data['error']['message'] ?? 'DeepInfra-verzoek mislukt.',
        'deepinfra_status' => $httpCode
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$reply = trim($data['choices'][0]['message']['content'] ?? '');

if ($reply === '') {
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'error' => 'DeepInfra gaf geen antwoordtekst terug.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => $reply
], JSON_UNESCAPED_UNICODE);
