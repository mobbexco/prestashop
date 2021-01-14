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
            width: fit-content;
            padding: 4px 18px;
            font-size: 17px;
            color: {/literal}{$style_settings['text_color']}{literal};
            background: {/literal}{$style_settings['background']}{literal};
            border: none;
            border-radius: 6px;
            cursor: pointer;
            box-shadow: 2px 2px 4px 0 rgba(0,0,0,.2);
        }
        #close-mobbex-plans {
            position: fixed;
            top: 20px; right: 20px;
            font-size: large;
            color: white;
            background: none;
            border: none;
            cursor: pointer;
            opacity: 1;
            z-index: 10000;
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
    <button type="button" id="open-mobbex-plans">{$style_settings['text']}<img src="https://res.mobbex.com/images/sources/mobbex.png" width="40" height="40" style="margin-left: 15px; border-radius: 40px;"></button>
    <div class="mobbex-plans-modal">
        <button type="button" id="close-mobbex-plans"></button>
        <iframe id="iframe" src="https://mobbex.com/p/sources/widget/arg/{$tax_id}?total={$price_amount}" title="mobbex-plans-iframe"></iframe>
    </div>
    
        <script>
        var mobbexPlansOpen  = document.getElementById('open-mobbex-plans');
        var mobbexPlansClose = document.getElementById('close-mobbex-plans');
        var mobbexPlansModal = document.querySelector('.mobbex-plans-modal');
        var iframe = document.getElementById('iframe');
        //retrieve smarty variables
        var price_one = "{$price_amount}";
        var tax_id = "{$tax_id}";

        mobbexPlansOpen.addEventListener('click', function() {
            mobbexPlansModal.classList.toggle('active');
            // get the quantity selected
            var quantity = $('#quantity_wanted').eq(0).val();
            if(quantity > 1){
                //recalculate the price based on quantity
                var total_price = price_one * quantity;
                iframe.src = "https://mobbex.com/p/sources/widget/arg/"+tax_id+"?total="+total_price;
            }
            document.querySelector('body').classList.toggle('scroll-lock');
        });
        
        mobbexPlansClose.addEventListener('click', function() {
            mobbexPlansModal.classList.toggle('active');
            document.querySelector('body').classList.toggle('scroll-lock');
        });

        </script>
    
</div>