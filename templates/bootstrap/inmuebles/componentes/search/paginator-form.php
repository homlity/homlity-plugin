<div class="row">
<div class="col-md-12">
    <br>
    <?php if (isset($paginador)) {
        if ($paginador->getNumPages() > 1) { ?>
            <nav aria-label="Page navigation example">
                <ul class="pagination justify-content-end">
                    <?php if ($paginador->getPrevUrl()): ?>
                        <li class="page-item">
                            <a href="<?php echo htmlspecialchars($paginador->getPrevUrl()) ?>" data-toggle="tooltip"
                               data-placement="top" title="<?php echo $paginador->getPreviousText() ?>" class="page-link">
                                <i class="icon-homlity icon-uniEA40"></i><?php echo $paginador->getPreviousText() ?></a></li>
                    <?php endif; ?>

                    <?php foreach ($paginador->getPages() as $page) {
                        if ($page['url']) { ?>
                            <li class="page-item <?php echo($page["isCurrent"] ? 'active' : '') ?> " <?php echo($page["isCurrent"] ? 'aria-current="page"' : '') ?>   aria-label="Pagina <?php echo $page["num"] ?>" >
                                <a class="page-link "
                                 
                                   href="<?php echo htmlspecialchars($page['url']) ?>"><?php echo $page["num"] ?></a>
                            </li>
                            <?php
                        } else { ?>
                            <li><span class="pagination-ellipsis">&hellip;</span></li>
                            <?php
                        }
                    }
                    ?>
                    <?php if ($paginador->getNextUrl()): ?>
                        <li class="page-item">
                            <a href="<?php echo htmlspecialchars($paginador->getNextUrl()) ?>"
                               data-toggle="tooltip" data-placement="top" title="<?php echo $paginador->getNextText() ?>"
                               class="page-link"><?php echo $paginador->getNextText() ?><i class="icon-homlity icon-uniEA3C"></i></a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php }
    } ?>
</div>
</div>
