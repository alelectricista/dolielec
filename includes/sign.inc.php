<?php
// sig_form.inc.php – formulario de firma de PDF con certificado digital
global $langs, $conf;
$langs->load('dolielec@dolielec');

$cert_file = $conf->global->DOLIELEC_CERT_FILE;

print '<form id="SignPdf" enctype="multipart/form-data" method="POST" action="'.dol_buildpath('/custom/dolielec/doc.php?tab=signature',1).'">';
print '  <input type="hidden" name="action" value="">';
print '  <div class="div-table-responsive-no-min">';
print '    <table class="noborder centpercent">';
print '      <tr class="liste_titre">';
print '        <th class="titlefield">'.$langs->trans('DocumentToSign').'</th>';
print '        <th>'.$langs->trans('Value').'</th>';
print '      </tr>';
print '      <tr>';
print '        <td><label for="file_pdf">'.$langs->trans('ChoosePDF').' *</label></td>';
print '        <td><input type="file" id="file_pdf" name="file_pdf" class="minwidth200" required accept=".pdf">';
if ($cert_file) {
    print '<br><span class="opacitymedium">'.$langs->trans('CertFile').': '.dol_escape_htmltag($cert_file).'</span>';
}
print '        </td>';
print '      </tr>';
print '    </table>';
print '  </div>';
print '  <div class="center">';
print '<button type="button" id="signPdfBtn" class="button button-sign">'.$langs->trans('SignPDF').'</button>';
print '  </div>';
print '</form>';

// Carga JS para firma
print '<script type="text/javascript" src="'.dol_buildpath('/custom/dolielec/js/doc.js.php',1).'"></script>';
?>
