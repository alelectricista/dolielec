<?php
// dolielec.css.php - CSS accesible para el módulo Dolielec (bloque 17)
header("Content-type: text/css");

// Estilo para el asterisco de campo obligatorio
?>
.fieldrequired {
    color: #B00020; /* Alto contraste rojo */
    font-weight: bold;
    margin-right: 5px;
    font-size: 1.1em;
}
input[required], select[required], textarea[required] {
    border-left: 4px solid #B00020;
}
input:focus-visible, select:focus-visible, textarea:focus-visible, button:focus-visible {
    outline: 3px solid #005fcc;
    outline-offset: 2px;
}
.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    overflow: hidden;
    clip: rect(0,0,0,0);
    white-space: nowrap;
    border: 0;
}

/* === Accesibilidad – helpers comunes === */
.visually-hidden{position:absolute!important;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
.skip-link{position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden}
.skip-link:focus{position:static;width:auto;height:auto;display:inline-block;padding:.5rem 1rem;border:2px solid currentColor;border-radius:.2rem;background:transparent}
.biel-identity{max-width:70ch;line-height:1.6}
.biel-identity a{text-decoration:underline;text-underline-offset:2px;text-decoration-thickness:2px}
.biel-identity a:focus-visible{outline:3px solid currentColor;outline-offset:3px;border-radius:.2rem}
.biel-identity h2,.biel-identity h3{margin:1rem 0 .25rem 0}
.biel-identity ul{padding-left:1.25rem}
.biel-identity li{margin:.25rem 0}
.biel-identity code{padding:.1rem .3rem;border:1px solid #888;border-radius:.2rem}
@media (max-width: 360px){ .biel-identity{max-width:100%;word-break:break-word;overflow-wrap:anywhere} }
