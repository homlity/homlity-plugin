<style>
    @media (max-width: 700px) {
        .mapa-inmueble {
            width: 96%;
        }
    }
</style>
<div class="mapa-inmueble">
    
    <div id="visualinmueble-map">
        <visualinmueble-map></visualinmueble-map>
    </div>
    <script type="text/javascript">
        window.VISUALINMUEBLE_INMUEBLES = <?php echo $inmuebles; ?>;
    </script>
</div>