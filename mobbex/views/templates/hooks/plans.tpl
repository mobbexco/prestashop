<div class="mobbex-plans">
    {literal}
    <style>
        .mobbex-plans {
            padding: 10px;
        }
        .mobbex-plans-modal {
            display: none;
            place-items: center;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            overflow: hidden;
            box-sizing: border-box;
            background: rgb(0 0 0 / .4);
            transition: all ease-in-out .3s;
            z-index: 9999;
        }
        .mobbex-plans-modal.active {
            display: grid;
        }
        .mobbex-plans-modal iframe {
            max-width: 665px; max-height: 700px;
            height: 100%; width: 100%;
            background: #f3f3f3;
        }
        #open-mobbex-plans {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 215px;
            padding: 4px 18px;
            font-size: 17px;
            color: {/literal}{$style_settings['text_color']}{literal};
            background: {/literal}{$style_settings['background']}{literal};
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        #close-mobbex-plans {
            position: fixed;
            top: 20px; right: 20px;
            font-size: large;
            color: white;
            background: none;
            border: none;
            cursor: pointer;
            opacity: .6;
        }
        #close-mobbex-plans::after, #close-mobbex-plans::before {
            content: ' ';
            position: absolute;
            left: 8px;
            height: 16px;
            width: 2px;
            background-color: white;
            transform: rotate(45deg);
        }
        #close-mobbex-plans::before{
            transform: rotate(-45deg);
        }
        #close-mobbex-plans:hover{
            opacity: 1;
        }
        .scroll-lock {
            padding-right: 17px;
            overflow: hidden;
        }
    </style>
    {/literal}
    <button type="button" id="open-mobbex-plans">Planes Mobbex<img src="https://res.mobbex.com/images/sources/mobbex.png" width="40" height="40" style="margin-left: 15px; border-radius: 40px;"></button>
    <div class="mobbex-plans-modal">
        <button type="button" id="close-mobbex-plans"></button>
        <iframe src="https://mobbex.com/p/sources/widget/arg/20339969532?total={$price_amount}" title="mobbex-plans-iframe"></iframe>
    </div>
    {literal}
        <script>
        var mobbexPlansOpen  = document.getElementById('open-mobbex-plans');
        var mobbexPlansClose = document.getElementById('close-mobbex-plans');
        var mobbexPlansModal = document.querySelector('.mobbex-plans-modal');

        mobbexPlansOpen.addEventListener('click', function() {
            mobbexPlansModal.classList.toggle('active');
            document.querySelector('body').classList.toggle('scroll-lock');
        });
        mobbexPlansClose.addEventListener('click', function() {
            mobbexPlansModal.classList.toggle('active');
            document.querySelector('body').classList.toggle('scroll-lock');
        });
        </script>
    {/literal}
</div>