<?php
// Questo file è un template PHP e si aspetta che le variabili
// $nome_cliente, $id_ordine_display, $data_ordine, $indirizzo_spedizione (array),
// $articoli (array di oggetti/array), $subtotale, $costo_spedizione, $totale_ordine,
// $metodo_pagamento, $metodo_spedizione (opzionale), $logo_url, $nome_negozio, $url_negozio_homepage
// siano definite tramite extract() prima di includerlo.

// Funzione helper per formattare i prezzi
if (!function_exists('formatPriceEmail')) {
    function formatPriceEmail($price) {
        return '€' . number_format(floatval($price), 2, ',', '.');
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conferma Ordine <?php echo htmlspecialchars($id_ordine_display); ?></title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
        }
        .email-container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border: 1px solid #dddddd;
            border-radius: 8px;
            overflow: hidden;
        }
        .email-header {
            background-color: #1abc9c; /* Colore primario del tuo negozio */
            color: #ffffff;
            padding: 20px;
            text-align: center;
        }
        .email-header img.logo {
            max-width: 180px;
            margin-bottom: 10px;
        }
        .email-header h1 {
            margin: 0;
            font-size: 24px;
        }
        .email-body {
            padding: 25px;
        }
        .email-body h2 {
            color: #1abc9c;
            font-size: 20px;
            margin-top: 0;
            margin-bottom: 15px;
            border-bottom: 2px solid #eeeeee;
            padding-bottom: 8px;
        }
        .email-body p {
            margin-bottom: 15px;
            font-size: 16px;
        }
        .order-details-table, .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .order-details-table th, .order-details-table td,
        .summary-table th, .summary-table td {
            border: 1px solid #dddddd;
            padding: 10px;
            text-align: left;
            font-size: 15px;
        }
        .order-details-table th {
            background-color: #f9f9f9;
            font-weight: bold;
        }
        .order-details-table td.product-image-cell {
            width: 70px;
            text-align: center;
        }
        .order-details-table img.product-image {
            max-width: 60px;
            height: auto;
            border: 1px solid #eeeeee;
            border-radius: 4px;
        }
        .order-details-table .product-name {
            font-weight: bold;
        }
        .order-details-table .product-details {
            font-size: 0.9em;
            color: #555555;
        }
        .summary-table th {
            width: 60%;
        }
        .summary-table td {
            text-align: right;
        }
        .summary-table .total-row td {
            font-weight: bold;
            font-size: 18px;
            color: #1abc9c;
        }
        .address-section {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #eeeeee;
        }
        .address-section h3 {
            margin-top: 0;
            font-size: 17px;
            color: #333;
        }
        .button-link {
            display: inline-block;
            background-color: #1abc9c;
            color: #ffffff !important; /* Important per sovrascrivere stili link */
            padding: 12px 25px;
            text-decoration: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            margin-top: 15px;
            text-align: center;
        }
        .email-footer {
            background-color: #eeeeee;
            color: #777777;
            padding: 20px;
            text-align: center;
            font-size: 13px;
        }
        .email-footer a {
            color: #1abc9c;
            text-decoration: none;
        }

        /* Responsive styles */
        @media screen and (max-width: 600px) {
            .email-container {
                width: 100% !important;
                margin: 0 auto;
                border-radius: 0;
            }
            .email-body {
                padding: 20px;
            }
            .order-details-table td, .order-details-table th {
                 font-size: 14px;
                 padding: 8px;
            }
             .order-details-table .product-name {
                display: block; /* Stack product name and details on small screens */
                margin-bottom: 3px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <?php if (isset($logo_url) && !empty($logo_url)): ?>
                <img src="<?php echo htmlspecialchars($logo_url); ?>" alt="<?php echo htmlspecialchars($nome_negozio); ?> Logo" class="logo">
            <?php endif; ?>
            <h1>Grazie per il tuo ordine!</h1>
        </div>

        <div class="email-body">
            <p>Ciao <strong><?php echo htmlspecialchars($nome_cliente); ?></strong>,</p>
            <p>Abbiamo ricevuto il tuo ordine <strong><?php echo htmlspecialchars($id_ordine_display); ?></strong> effettuato il <?php echo htmlspecialchars($data_ordine); ?>. Stiamo preparando i tuoi articoli per la spedizione.</p>

            <h2>Riepilogo Ordine</h2>
            <table class="order-details-table">
                <thead>
                    <tr>
                        <th colspan="2">Articolo</th>
                        <th>Quantità</th>
                        <th>Prezzo Unit.</th>
                        <th>Subtotale</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($articoli as $articolo): ?>
                    <tr>
                        <td class="product-image-cell">
                            <?php if (isset($articolo->imageSmall) && !empty($articolo->imageSmall)): ?>
                                <img src="<?php echo htmlspecialchars($articolo->imageSmall); ?>" alt="<?php echo htmlspecialchars($articolo->name); ?>" class="product-image">
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="product-name"><?php echo htmlspecialchars($articolo->name); ?></span>
                            <?php
                            $details = [];
                            if (isset($articolo->size) && $articolo->size !== 'Unica' && !empty(trim($articolo->size))) $details[] = "Taglia: " . htmlspecialchars($articolo->size);
                            if (isset($articolo->color) && $articolo->color !== 'Unico' && !empty(trim($articolo->color))) $details[] = "Colore: " . htmlspecialchars($articolo->color);
                            ?>
                            <?php if (!empty($details)): ?>
                                <div class="product-details"><?php echo implode(', ', $details); ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;"><?php echo htmlspecialchars($articolo->quantity); ?></td>
                        <td style="text-align:right;"><?php echo formatPriceEmail($articolo->price); ?></td>
                        <td style="text-align:right;"><?php echo formatPriceEmail($articolo->price * $articolo->quantity); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <table class="summary-table">
                <tr>
                    <th>Subtotale Articoli:</th>
                    <td><?php echo formatPriceEmail($subtotale); ?></td>
                </tr>
                <tr>
                    <th>Costo Spedizione (<?php echo htmlspecialchars($metodo_spedizione ?? ''); ?>):</th>
                    <td><?php echo formatPriceEmail($costo_spedizione); ?></td>
                </tr>
                <tr class="total-row">
                    <th>Totale Ordine:</th>
                    <td><?php echo formatPriceEmail($totale_ordine); ?></td>
                </tr>
            </table>

            <div class="address-section">
                <h3>Indirizzo di Spedizione:</h3>
                <p>
                    <?php echo htmlspecialchars($indirizzo_spedizione['nome']); ?><br>
                    <?php echo htmlspecialchars($indirizzo_spedizione['via']); ?><br>
                    <?php echo htmlspecialchars($indirizzo_spedizione['cap']); ?> <?php echo htmlspecialchars($indirizzo_spedizione['citta']); ?><br>
                    <?php if (!empty($indirizzo_spedizione['telefono'])): ?>
                        Tel: <?php echo htmlspecialchars($indirizzo_spedizione['telefono']); ?><br>
                    <?php endif; ?>
                </p>
            </div>

            <div class="address-section">
                 <h3>Metodo di Pagamento:</h3>
                 <p><?php echo htmlspecialchars($metodo_pagamento); ?></p>
            </div>

            <?php if ($metodo_pagamento === 'Bonifico Bancario'): ?>
            <div class="address-section" style="background-color: #fff3cd; border-color: #ffeeba;">
                 <h3 style="color: #856404;">Istruzioni per Bonifico Bancario</h3>
                 <p>Effettua il pagamento tramite bonifico bancario utilizzando i seguenti dati:</p>
                 <p>
                     <strong>Beneficiario:</strong> E-Shop Kids S.R.L.<br>
                     <strong>IBAN:</strong> IT00A1234512345123456789012<br>
                     <strong>Banca:</strong> Banca Esempio S.p.A.<br>
                     <strong>Causale:</strong> Pagamento Ordine <?php echo htmlspecialchars($id_ordine_display); ?>
                 </p>
                 <p>Il tuo ordine verrà elaborato non appena riceveremo conferma del pagamento.</p>
            </div>
            <?php endif; ?>

            <p>Puoi visualizzare i dettagli del tuo ordine e seguirne lo stato accedendo al tuo account sul nostro sito.</p>
            <div style="text-align: center;">
                <a href="<?php echo htmlspecialchars($url_negozio_homepage); ?>" class="button-link">Vai al Negozio</a>
            </div>

            <p>Grazie ancora per aver scelto <?php echo htmlspecialchars($nome_negozio); ?>!</p>
        </div>

        <div class="email-footer">
            <p>© <?php echo date('Y'); ?> <?php echo htmlspecialchars($nome_negozio); ?>. Tutti i diritti riservati.</p>
            <p>
                <a href="<?php echo htmlspecialchars($url_negozio_homepage); ?>">Visita il nostro negozio</a> |
                <a href="<?php echo htmlspecialchars($url_negozio_homepage . (strpos($url_negozio_homepage, '?') === false ? '?' : '&') . 'sezione=contatti'); // Esempio se avessi una pagina contatti ?> ">Contattaci</a>
            </p>
            <?php /* Potresti aggiungere qui l'indirizzo fisico del negozio se necessario */ ?>
        </div>
    </div>
</body>
</html>