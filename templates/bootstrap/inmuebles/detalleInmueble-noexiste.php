<h1>INMUEBLE NO EXISTE</h1>
<?php 
if(isset($message)):
?>
<p><?php echo $message; ?></p>
<?php endif; ?>
<hr>
<?php visualinmu_load_template("inmuebles/componentes/search/search-widgets.php"); ?>