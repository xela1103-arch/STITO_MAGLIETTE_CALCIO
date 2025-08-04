<?php
// ------------- INIZIO LOGICA PHP -------------
$messaggio_feedback = '';
$tipo_feedback = ''; // 'successo' o 'errore'

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['invia'])) {
    
    // 1. Imposta il destinatario
    $destinatario = "alexander.zed@hotmail.it"; // <<<--- MODIFICA QUESTO!

    // 2. Raccogli e sanifica i dati del form
    // Usiamo filter_var per una sanificazione base. Per maggiore sicurezza, considera librerie o regex più specifici.
    $nome = isset($_POST['nome']) ? filter_var(trim($_POST['nome']), FILTER_SANITIZE_STRING) : '';
    $email_mittente = isset($_POST['email']) ? filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL) : '';
    $oggetto_form = isset($_POST['oggetto']) ? filter_var(trim($_POST['oggetto']), FILTER_SANITIZE_STRING) : '';
    $messaggio_utente = isset($_POST['messaggio']) ? filter_var(trim($_POST['messaggio']), FILTER_SANITIZE_STRING) : ''; // FILTER_SANITIZE_FULL_SPECIAL_CHARS è più sicuro se si renderizza HTML

    // 3. Validazione base
    $errori = [];
    if (empty($nome)) {
        $errori[] = "Il nome è obbligatorio.";
    }
    if (empty($email_mittente)) {
        $errori[] = "L'email è obbligatoria.";
    } elseif (!filter_var($email_mittente, FILTER_VALIDATE_EMAIL)) {
        $errori[] = "L'indirizzo email non è valido.";
    }
    if (empty($oggetto_form)) {
        $errori[] = "L'oggetto è obbligatorio.";
    }
    if (empty($messaggio_utente)) {
        $errori[] = "Il messaggio è obbligatorio.";
    }

    // 4. Se non ci sono errori, prova a inviare l'email
    if (empty($errori)) {
        $oggetto_email = "Nuovo messaggio dal form contatti: " . $oggetto_form;
        
        $corpo_email = "Hai ricevuto un nuovo messaggio dal tuo form contatti:\n\n";
        $corpo_email .= "Nome: " . $nome . "\n";
        $corpo_email .= "Email: " . $email_mittente . "\n";
        $corpo_email .= "Oggetto: " . $oggetto_form . "\n";
        $corpo_email .= "Messaggio:\n" . $messaggio_utente . "\n";

        // Headers
        // È cruciale impostare il mittente (From) e il Reply-To per poter rispondere direttamente.
        $headers = "From: " . $nome . " <" . $email_mittente . ">\r\n";
        $headers .= "Reply-To: " . $email_mittente . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n"; // Per testo semplice

        // Invia l'email
        if (mail($destinatario, $oggetto_email, $corpo_email, $headers)) {
            $messaggio_feedback = "Grazie! Il tuo messaggio è stato inviato con successo.";
            $tipo_feedback = "successo";
            // Resetta i campi del form (opzionale, ma buona UX)
            $_POST = array(); 
        } else {
            $messaggio_feedback = "Spiacenti, si è verificato un errore durante l'invio del messaggio. Riprova più tardi.";
            $tipo_feedback = "errore";
        }
    } else {
        // Ci sono errori di validazione
        $messaggio_feedback = "Attenzione! Correggi i seguenti errori:<br>" . implode("<br>", $errori);
        $tipo_feedback = "errore";
    }
}
// ------------- FINE LOGICA PHP -------------
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modulo Contatti Moderno</title>
    <style>
        /* ------------- INIZIO CSS ------------- */
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f0f2f5; /* Un grigio chiaro moderno */
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: #333;
        }

        .contact-form-container {
            background-color: #ffffff;
            padding: 30px 40px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 550px;
            box-sizing: border-box;
        }

        .contact-form-container h1 {
            color: #2c3e50; /* Blu scuro */
            text-align: center;
            margin-bottom: 25px;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 8px;
            color: #555;
        }

        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 16px;
            color: #333;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .form-group input[type="text"]:focus,
        .form-group input[type="email"]:focus,
        .form-group textarea:focus {
            border-color: #007bff; /* Blu primario */
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
            outline: none;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .submit-btn {
            background-color: #007bff; /* Blu primario */
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
            display: block;
            width: 100%;
        }

        .submit-btn:hover {
            background-color: #0056b3; /* Blu più scuro per hover */
            transform: translateY(-2px);
        }

        .submit-btn:active {
            transform: translateY(0);
        }
        
        .feedback-message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-size: 15px;
            text-align: center;
        }
        .feedback-message.successo {
            background-color: #d4edda; /* Verde chiaro per successo */
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .feedback-message.errore {
            background-color: #f8d7da; /* Rosso chiaro per errore */
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        /* ------------- FINE CSS ------------- */
    </style>
</head>
<body>

    <div class="contact-form-container">
        <h1>Contattaci</h1>

        <?php if (!empty($messaggio_feedback)): ?>
            <div class="feedback-message <?php echo $tipo_feedback; ?>">
                <?php echo $messaggio_feedback; ?>
            </div>
        <?php endif; ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
            <div class="form-group">
                <label for="nome">Il tuo Nome:</label>
                <input type="text" id="nome" name="nome" required 
                       value="<?php echo isset($_POST['nome']) ? htmlspecialchars($_POST['nome']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="email">La tua Email:</label>
                <input type="email" id="email" name="email" required
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="oggetto">Oggetto:</label>
                <input type="text" id="oggetto" name="oggetto" required
                       value="<?php echo isset($_POST['oggetto']) ? htmlspecialchars($_POST['oggetto']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="messaggio">Messaggio:</label>
                <textarea id="messaggio" name="messaggio" rows="5" required><?php echo isset($_POST['messaggio']) ? htmlspecialchars($_POST['messaggio']) : ''; ?></textarea>
            </div>

            <button type="submit" name="invia" class="submit-btn">Invia Messaggio</button>
        </form>
    </div>

</body>
</html>