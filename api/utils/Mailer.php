<?php
// api/utils/Mailer.php
class Mailer {
    
    // Configuración global provista (podría venir de .env)
    private static $fromEmail = "no-reply@psycopanda.com";
    private static $fromName = "Psycopanda Evaluaciones";

    /**
     * Envía email genérico
     */
    private static function send($to, $subject, $bodyHtml) {
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . self::$fromName . " <" . self::$fromEmail . ">\r\n";

        // En un entorno de desarrollo local WAMP/XAMPP, mail() puede fallar si no hay un servidor SMTP.
        // Simulamos envío exitoso o ejecutamos mail si está configurado
        if (strpos($_SERVER['SERVER_SOFTWARE'] ?? '', 'Development') !== false || $_SERVER['SERVER_ADDR'] === '127.0.0.1' || $_SERVER['SERVER_ADDR'] === '::1') {
            error_log("MAIL_SIMULATOR: To: $to | Subject: $subject");
            return true;
        }

        return @mail($to, $subject, $bodyHtml, $headers);
    }

    /**
     * Escenario 1: Invitación a rendir prueba
     */
    public static function sendCandidateInvite($toEmail, $candidateName, $companyName, $evalName, $link, $password, $template) {
        $subject = $template['subject'] ?? "Invitación a evaluación - $companyName";
        $body = $template['bodyHtml'] ?? "Ingresa a $link con la contraseña $password";

        // Reemplazar variables (Tokens)
        $subject = str_replace('{{NOMBRE_EMPRESA}}', $companyName, $subject);
        $subject = str_replace('{{NOMBRE_EVALUACION}}', $evalName, $subject);
        $subject = str_replace('{{NOMBRE_POSTULANTE}}', $candidateName, $subject);

        $body = str_replace('{{NOMBRE_EMPRESA}}', $companyName, $body);
        $body = str_replace('{{NOMBRE_EVALUACION}}', $evalName, $body);
        $body = str_replace('{{NOMBRE_POSTULANTE}}', $candidateName, $body);
        $body = str_replace('{{LINK_EVALUACION}}', $link, $body);
        $body = str_replace('{{EMAIL_POSTULANTE}}', $toEmail, $body);
        $body = str_replace('{{PASSWORD_POSTULANTE}}', $password, $body);

        return self::send($toEmail, $subject, $body);
    }

    /**
     * Escenario 2: Notificación Evaluación Finalizada para Empresa
     */
    public static function sendEvaluationCompleted($companyEmail, $candidateName, $evalName) {
        $subject = "Evaluación Completada: $candidateName";
        $body = "<h3>¡El candidato ha terminado!</h3>";
        $body .= "<p>El candidato <strong>$candidateName</strong> acaba de finalizar la evaluación <strong>$evalName</strong>.</p>";
        $body .= "<p>Puedes ingresar al dashboard de Psycopanda para revisar los resultados detallados.</p>";

        return self::send($companyEmail, $subject, $body);
    }

    /**
     * Escenario 3: Credenciales primera vez (Registro empresa)
     */
    public static function sendFirstTimeCredentials($toEmail, $firstName, $password) {
        $subject = "Bienvenido a Psycopanda - Tus credenciales de acceso";
        $body = "<h3>¡Bienvenido $firstName!</h3>";
        $body .= "<p>Tu cuenta de empresa ha sido creada exitosamente.</p>";
        $body .= "<p><strong>Usuario:</strong> $toEmail<br><strong>Contraseña temporal:</strong> $password</p>";
        $body .= "<p>Te recomendamos cambiar tu contraseña al iniciar sesión por primera vez.</p>";

        return self::send($toEmail, $subject, $body);
    }

    /**
     * Escenario 4: Envío de enlace de configuración (equipo creado desde admin)
     */
    public static function sendTeamInvite($toEmail, $firstName, $companyName, $inviteLink) {
        $subject = "Te han invitado a unirte a Psycopanda";
        $body = "<h3>Hola $firstName</h3>";
        $body .= "<p>Has sido invitado a unirte al equipo de <strong>$companyName</strong> en Psycopanda.</p>";
        $body .= "<p>Para comenzar y configurar tu contraseña, ingresa al siguiente enlace:</p>";
        $body .= "<p><a href=\"$inviteLink\">Configurar cuenta</a></p>";

        return self::send($toEmail, $subject, $body);
    }

    /**
     * Escenario 5: Restablecer contraseña
     */
    public static function sendPasswordReset($toEmail, $resetLink) {
        $subject = "Restablecer tu contraseña de Psycopanda";
        $body = "<h3>Recuperación de contraseña</h3>";
        $body .= "<p>Hemos recibido una solicitud para restablecer la contraseña de tu cuenta.</p>";
        $body .= "<p><a href=\"$resetLink\">Haz clic aquí para crear una nueva contraseña</a></p>";
        $body .= "<p>Si no solicitaste esto, puedes ignorar este correo.</p>";

        return self::send($toEmail, $subject, $body);
    }

    /**
     * Escenario 6: Resumen Diario
     */
    public static function sendDailySummary($companyEmail, $companyName, $summaryDataHtml) {
        $subject = "Psycopanda: Resumen Diario de Evaluaciones";
        $body = "<h3>Resumen Diario - $companyName</h3>";
        $body .= "<p>A continuación te presentamos un resumen de las evaluaciones completadas en las últimas 24 horas:</p>";
        $body .= $summaryDataHtml;
        
        return self::send($companyEmail, $subject, $body);
    }
}
?>
