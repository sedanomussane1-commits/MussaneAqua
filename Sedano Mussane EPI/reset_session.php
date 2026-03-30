<?php
// reset_session.php
session_start();
session_destroy();
echo "Sessão destruída. <a href='public/login.php'>Ir para login</a>";
?>