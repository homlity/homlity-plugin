<section class="visualinmu_password_form d-flex justify-content-center align-items-center min-vh-100">
    <div class="text-center p-4 border rounded shadow-sm bg-light">

        <p class="post-password-message">Este contenido(<?php echo $tag; ?>) está protegido con contraseña. Por favor, introduce la
            contraseña para verlo.</p>
        <form action="<?php echo esc_url( site_url( 'wp-login.php?action=homlity_tag_pw', 'login_post' ) )?>" class="post-password-form" method="post">
            <label class="post-password-form__label" for="pwbox-53">Contraseña</label><input
                class="post-password-form__input" name="post_password" id="pwbox-53" type="password" size="20">
                <input type="submit" class="post-password-form__submit" name="Enviar" value="Enter">
                <input type="hidden" name="homlity_tag_hash" value="<?php echo $hash;?>">
        </form>
    </div>
</section>
</section>