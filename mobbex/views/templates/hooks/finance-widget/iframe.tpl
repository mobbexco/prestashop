<div class="mobbex-plans">
    <button type="button" id="mbbxProductBtn" class="{if $style_settings['default_styles']}btn btn-secondary mt-1{else}mbbxWidgetOpenBtn{/if}">
        {if !empty($style_settings['button_image'])}
            <img src="{$style_settings['button_image']}" 
                width="40" 
                height="40"
                style="margin-right: 15px; border-radius: 40px;">
        {/if}
        {$style_settings['text']}
    </button>
    <div id="modal-mobbex">
        <div id="mbbx-modal-content">
            <span id="mbbx-close-widget">&times;</span>
            <iframe id="mbbx-iframe" src="{$iframe_url}"></iframe>
        </div>
    </div>
</div>

<style>
    {$style_settings['styles']}{literal}
    .mobbex-plans div {
        margin: 0;
        color: rgb(35, 35, 35);
        font-size: 15px;
    }

    #modal-mobbex {
        position: fixed;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: hidden;
        background-color: rgba(0, 0, 0, 0.4);
        z-index: 9999;
        display: none;
        place-items: center;
    }

    #mbbx-modal-content {
        background: rgb(236, 242, 246);
        border-radius: 10px;
        z-index: 10000;
        width: 100%;
        height: 90%;
        max-width: 600px;
        padding: 20px;
        display: flex;
        flex-flow: column;
        align-items: end;
    }

    #mbbx-iframe {
        width: 100%;
        height: 100%;
        border: none;
        border-radius: 16px;
    }

    #mbbx-close-widget {
        color: #4e4e4e;
        font-size: 30px;
        width: 24px;
        text-align: center;
        border-radius: 100%;
        margin: 0;
        height: 24px;
        text-decoration: none;
    }

    #mbbx-close-widget:hover,
    #mbbx-close-widget:focus {
        color: black;
        cursor: pointer;
    }

    .scroll-lock {
        padding-right: 17px;
        overflow: hidden;
    }
    {/literal}
</style>
<script>
    (function (window) {
        let modal = document.getElementById("modal-mobbex");
        let open  = document.getElementById('mbbxProductBtn');
        let close = document.getElementById('mbbx-close-widget');

        document.addEventListener('click', function(event) {
            if (event.target == modal || event.target == close) {
                modal.style.display = "none";
            } else if (event.target == open) {
                modal.style.display = "grid";
            } else {
                return;
            }

            document.body.classList.toggle('scroll-lock');
        });
    }) (window);
</script>