<?php
// Template PHP per email di notifica nuovo ordine all'amministratore.
// Variabili attese (estratte prima dell'include):
// $id_ordine_db, $id_ordine_display, $data_ordine, $nome_cliente, $email_cliente,
// $indirizzo_spedizione (array), $articoli (array di oggetti/array),
// $subtotale, $costo_spedizione, $totale_ordine, $metodo_pagamento, $metodo_spedizione,
// $logo_url, $nome_negozio, $admin_order_management_url (con ?order_id=...)

if (!function_exists('formatPriceEmailAdmin')) { // Definisci se non già definita (potrebbe esserlo da altro template)
    function formatPriceEmailAdmin($price) {
        return '€' . number_format(floatval($price), 2, ',', '.');
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuovo Ordine Ricevuto: <?php echo htmlspecialchars($id_ordine_display); ?></title>
    <style>
        body { margin: 0; padding: 0; background-color: #e9e9e9; font-family: Arial, sans-serif; line-height: 1.6; color: #333333; }
        .email-wrapper { background-color: #ffffff; max-width: 680px; margin: 20px auto; border: 1px solid #cccccc; border-radius: 8px; overflow: hidden; }
        .email-header { background-color: #2c3e50; /* Darker admin theme */ color: #ffffff; padding: 25px; text-align: center; }
        .email-header img.logo { max-width: 150px; margin-bottom: 10px; filter: brightness(0) invert(1); /* Invert logo for dark bg */ }
        .email-header h1 { margin: 0; font-size: 26px; }
        .email-body { padding: 20px 30px; }
        .email-body h2 { color: #1abc9c; font-size: 22px; margin-top: 0; margin-bottom: 18px; border-bottom: 2px solid #eeeeee; padding-bottom: 10px; }
        .email-body p { margin-bottom: 15px; font-size: 16px; }
        .info-section { background-color: #f8f9fa; padding: 18px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #e0e0e0; }
        .info-section h3 { margin-top: 0; font-size: 18px; color: #2c3e50; margin-bottom:10px; }
        .order-items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 15px; }
        .order-items-table th, .order-items-table td { border: 1px solid #dddddd; padding: 10px; text-align: left; }
        .order-items-table th { background-color: #e9ecef; font-weight: bold; }
        .order-items-table td.product-image-cell-admin { width: 50px; text-align: center; padding:5px; }
        .order-items-table img.product-image-admin { max-width: 45px; height: auto; border: 1px solid #e0e0e0; border-radius: 3px; }
        .order-items-table .item-details-admin { font-size: 0.9em; color: #555555; }
        .summary-table-admin { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .summary-table-admin th { width: 65%; text-align: right; padding: 8px; border-bottom: 1px solid #eee; font-weight: normal; color: #444;}
        .summary-table-admin td { text-align: right; padding: 8px; border-bottom: 1px solid #eee; font-weight: bold; }
        .summary-table-admin .grand-total-admin td { font-size: 20px; color: #1abc9c; border-top: 2px solid #333; padding-top:10px; }
        .action-button-container { text-align: center; margin: 30px 0; }
        .action-button { display: inline-block; background-color: #1abc9c; color: #ffffff !important; padding: 14px 30px; text-decoration: none; border-radius: 5px; font-size: 17px; font-weight: bold; }
        .email-footer { background-color: #f0f2f5; color: #888888; padding: 20px; text-align: center; font-size: 13px; }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="email-header">
            <?php if (isset($logo_url) && !empty($logo_url)): ?>
                <img src="<?php echo htmlspecialchars($logo_url); ?>" alt="<?php echo htmlspecialchars($nome_negozio); ?> Logo" class="logo">
            <?php endif; ?>
            <h1>Nuovo Ordine Ricevuto!</h1>
        </div>

        <div class="email-body">
            <p>Ciao Amministratore,</p>
            <p>È stato effettuato un nuovo ordine sul sito <strong><?php echo htmlspecialchars($nome_negozio); ?></strong>.</p>
            <p><strong>ID Ordine:</strong> <?php echo htmlspecialchars($id_ordine_display); ?> (Database ID: <?php echo htmlspecialchars($id_ordine_db); ?>)</p>
            <p><strong>Data Ordine:</strong> <?php echo htmlspecialchars($data_ordine); ?></p>

            <div class="action-button-container">
                <a href="<?php echo htmlspecialchars($admin_order_management_url); ?>" class="action-button">Gestisci Questo Ordine</a>
            </div>

            <h2>Dettagli Cliente</h2>
            <div class="info-section">
                <p><strong>Nome Cliente:</strong> <?php echo htmlspecialchars($nome_cliente); ?></p>
                <p><strong>Email Cliente:</strong> <a href="mailto:<?php echo htmlspecialchars($email_cliente); ?>"><?php echo htmlspecialchars($email_cliente); ?></a></p>
                <?php if (isset($indirizzo_spedizione['telefono']) && !empty($indirizzo_spedizione['telefono'])): ?>
                    <p><strong>Telefono Cliente:</strong> <?php echo htmlspecialchars($indirizzo_spedizione['telefono']); ?></p>
                <?php endif; ?>
            </div>

            <h2>Indirizzo di Spedizione</h2>
            <div class="info-section">
                <p>
                    <?php echo htmlspecialchars($indirizzo_spedizione['nome']); ?><br>
                    <?php echo htmlspecialchars($indirizzo_spedizione['via']); ?><br>
                    <?php echo htmlspecialchars($indirizzo_spedizione['cap']); ?> <?php echo htmlspecialchars($indirizzo_spedizione['citta']); ?>
                </p>
            </div>
            
            <h2>Dettaglio Articoli Ordinati</h2>
            <table class="order-items-table">
                <thead>
                    <tr>
                        <th colspan="2">Articolo</th>
                        <th>Qtà</th>
                        <th>Prezzo Un.</th>
                        <th>Subtotale</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($articoli as $articolo): ?>
                    <tr>
                        <td class="product-image-cell-admin">
                            <?php if (isset($articolo->imageSmall) && !empty($articolo->imageSmall)): ?>
                                <img src="<?php echo htmlspecialchars($articolo->imageSmall); ?>" alt="" class="product-image-admin">
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($articolo->name); ?></strong>
                            <?php
                            $item_details_admin = [];
                            if (isset($articolo->productId)) $item_details_admin[] = "ID Prod: " . htmlspecialchars($articolo->productId);
                            if (isset($articolo->size) && $articolo->size !== 'Unica' && !empty(trim($articolo->size))) $item_details_admin[] = "Tg: " . htmlspecialchars($articolo->size);
                            if (isset($articolo->color) && $articolo->color !== 'Unico' && !empty(trim($articolo->color))) $item_details_admin[] = "Col: " . htmlspecialchars($articolo->color);
                            ?>
                            <?php if (!empty($item_details_admin)): ?>
                                <div class="item-details-admin"><?php echo implode(' | ', $item_details_admin); ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;"><?php echo htmlspecialchars($articolo->quantity); ?></td>
                        <td style="text-align:right;"><?php echo formatPriceEmailAdmin($articolo->price); ?></td>
                        <td style="text-align:right;"><?php echo formatPriceEmailAdmin($articolo->price * $articolo->quantity); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h2>Riepilogo Costi</h2>
            <table class="summary-table-admin">
                <tr>
                    <th>Subtotale Articoli:</th>
                    <td><?php echo formatPriceEmailAdmin($subtotale); ?></td>
                </tr>
                <tr>
                    <th>Costo Spedizione (<?php echo htmlspecialchars($metodo_spedizione ?? ''); ?>):</th>
                    <td><?php echo formatPriceEmailAdmin($costo_spedizione); ?></td>
                </tr>
                <tr class="grand-total-admin">
                    <th>Totale Ordine:</th>
                    <td><?php echo formatPriceEmailAdmin($totale_ordine); ?></td>
                </tr>
            </table>

            <h2>Informazioni Aggiuntive</h2>
            <div class="info-section">
                <p><strong>Metodo di Pagamento Scelto:</strong> <?php echo htmlspecialchars($metodo_pagamento); ?></p>
            </div>
            
            <p>Controlla la dashboard di amministrazione per ulteriori dettagli e per processare l'ordine.</p>
        </div>

        <div class="email-footer">
            <p>Questa è una notifica automatica da <?php echo htmlspecialchars($nome_negozio); ?>.</p>
        </div>
    </div>
</body>
</html>