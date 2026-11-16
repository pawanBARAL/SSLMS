<?php

/*
|--------------------------------------------------------------------------
| Shared Footer
|--------------------------------------------------------------------------
|
| Contains the common footer, Bootstrap JavaScript and SSLMS interface
| behaviour.
|
|--------------------------------------------------------------------------
*/

?>


<footer class="app-footer">


    <div>


        <strong>

            SSLMS

        </strong>

        <span>

            Student Smart Life Management System

        </span>


    </div>


    <small>

        &copy;
        <?php echo date("Y"); ?>
        SSLMS

    </small>


</footer>


</div>


<!-- Bootstrap JavaScript -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
></script>


<script>

/*
|--------------------------------------------------------------------------
| Mobile Sidebar
|--------------------------------------------------------------------------
|
| Opens and closes the navigation sidebar on smaller screens.
|
|--------------------------------------------------------------------------
*/

document.addEventListener(
    "DOMContentLoaded",
    function () {

        const sidebar =
            document.getElementById(
                "sidebar"
            );

        const menuButton =
            document.getElementById(
                "mobileMenuButton"
            );

        const overlay =
            document.getElementById(
                "sidebarOverlay"
            );


        /*
        |--------------------------------------------------------------------------
        | Open Sidebar
        |--------------------------------------------------------------------------
        */

        if (
            menuButton &&
            sidebar &&
            overlay
        ) {

            menuButton.addEventListener(
                "click",
                function () {

                    sidebar.classList.add(
                        "sidebar-open"
                    );

                    overlay.classList.add(
                        "sidebar-overlay-visible"
                    );

                    document.body.classList.add(
                        "sidebar-active"
                    );
                }
            );


            /*
            |--------------------------------------------------------------------------
            | Close Sidebar by Clicking Overlay
            |--------------------------------------------------------------------------
            */

            overlay.addEventListener(
                "click",
                function () {

                    closeSidebar();

                }
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Close Sidebar After Selecting Link on Mobile
        |--------------------------------------------------------------------------
        */

        const sidebarLinks =
            document.querySelectorAll(
                ".sidebar-link"
            );


        sidebarLinks.forEach(
            function (link) {

                link.addEventListener(
                    "click",
                    function () {

                        if (
                            window.innerWidth
                            <=
                            991
                        ) {

                            closeSidebar();
                        }
                    }
                );
            }
        );


        /*
        |--------------------------------------------------------------------------
        | Close Sidebar Function
        |--------------------------------------------------------------------------
        */

        function closeSidebar() {

            if (sidebar) {

                sidebar.classList.remove(
                    "sidebar-open"
                );
            }


            if (overlay) {

                overlay.classList.remove(
                    "sidebar-overlay-visible"
                );
            }


            document.body.classList.remove(
                "sidebar-active"
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Escape Key
        |--------------------------------------------------------------------------
        */

        document.addEventListener(
            "keydown",
            function (event) {

                if (
                    event.key ===
                    "Escape"
                ) {

                    closeSidebar();
                }
            }
        );


        /*
        |--------------------------------------------------------------------------
        | Window Resize
        |--------------------------------------------------------------------------
        */

        window.addEventListener(
            "resize",
            function () {

                if (
                    window.innerWidth
                    >
                    991
                ) {

                    closeSidebar();
                }
            }
        );

    }
);

</script>


</body>

</html>