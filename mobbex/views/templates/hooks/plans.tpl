<div class="mobbex-plans">
    {literal}
        <style>
            /* CLOSE-OPEN BUTTONS */
            #mbbxProductBtn {
                display: flex;
                justify-content: space-between;
                align-items: center;
                width: fit-content;
                padding: {/literal}{if $style_settings['button_padding']}{$style_settings['button_padding']}{else}4px 18px{/if}{literal};
                font-size: {/literal}{if $style_settings['button_font_size']}{$style_settings['button_font_size']}{else}16px{/if}{literal};
                color: {/literal}{if $style_settings['text_color']}{$style_settings['text_color']}{else}#ffffff{/if}{literal};
                background: {/literal}{if $style_settings['background']}{$style_settings['background']}{else}#8900ff{/if}{literal};
                border: none;
                border-radius: 6px;
                cursor: pointer;
                box-shadow: 2px 2px 4px 0 rgba(0, 0, 0, .2);
                min-height: 40px;
            }
            #closembbxProduct {
                font-size: 35px;
                color: rgb(0, 0, 0);
                cursor: pointer;
                margin-bottom: 10px;
            }
            .dark #closembbxProduct {
                color: white;   
            }
            #closembbxProduct:hover {

                cursor: pointer;
            }

            .scroll-lock {
                padding-right: 17px;
                overflow: hidden;
            }

            /* MODAL STYLES */

            /* The Modal (background) */
            #mbbxProductModal {
                position: fixed;
                /* Stay in place */
                left: 0;
                top: 0;
                width: 100%;
                /* Full width */
                height: 100%;
                /* Full height */
                overflow: auto;
                /* Enable scroll if needed */
                background-color: rgb(0, 0, 0);
                /* Fallback color */
                background-color: rgba(0, 0, 0, 0.4);
                /* Black w/ opacity */
                z-index: 9999;
                place-items: center;
            }

            #mbbxProductModal.active {
                display: grid!important;
            }

            /* Modal Content/Box */
            #mbbxProductModalContent {
                background-color: #fefefe;
                padding: 20px;
                border: 1px solid #888;
                max-width: 650px;
                /* Could be more or less, depending on screen size */
                height: 90%;
                /* Full height */
                width: 100%;
                z-index: 10000;
                overflow-y: scroll;
                border-radius: 10px;
            }

            #mbbxProductModalHeader {
                display: flex;
                justify-content: space-between;
                flex-flow: wrap;
                align-items: center;
            }

            /* Modal Scrollbar */
            #mbbxProductModalContent::-webkit-scrollbar {
                width: 20px;
            }

            #mbbxProductModalContent::-webkit-scrollbar-track {
                background-color: transparent;
            }

            #mbbxProductModalContent::-webkit-scrollbar-thumb {
                background-color: #d6dee1;
                border-radius: 20px;
                border: 6px solid transparent;
                background-clip: content-box;
            }

            #mbbxProductModalContent::-webkit-scrollbar-thumb:hover {
                background-color: #a8bbbf;
            }

            .mobbexPaymentMethod {
                display: flex;
                align-items: center;
                padding: 1em 0;
                margin: 0;
                font-weight: bold;
            }

            .mobbexPaymentMethod img {
                height: 40px;
                border-radius: 100%;
                margin-right: 10px;
            }

            #mbbx-method-select {
                width: 100%;
                min-height: 40px;
                padding: 0.5rem;
                border: 1px #d8d8d8 solid;
                border-radius: 5px;
            }

            .installmentsTable {
                margin-bottom: 20px;
                width: 80%;
                margin: 0 auto;
            }

            .installmentsTable td {
                padding: 10px 0;
                text-align: start;
            }

            .mbbxPlansPrice {
                width: 30%;
                text-align: end !important;
            }

            /* DARK MODE  */
            .dark #mbbxProductModalContent,
            .dark #mbbxProductModalContent table td {
                background-color: rgb(39, 31, 36);
                color: rgb(226, 226, 226);
            }

            .dark #mbbxProductModalContent #mobbex_select_title,
            .dark #mbbxProductModalContent .mobbexPaymentMethod {
                color: rgb(226, 226, 226);
            }

        </style>
    {/literal}

    <div id="mbbxProductModal" class="mobbex-plans-modal {$style_settings['plans_theme']}" style="display: none;">
        <div id="mbbxProductModalContent" class="{$style_settings['plans_theme']}">
        <div id="mbbxProductModalHeader">
        <label id="mobbex_select_title" for="mbbx-method-select">Seleccione un método de pago</label>
        <span id="closembbxProduct">&times;</span>
                <select name="mbbx-method-select" id="mbbx-method-select">
                    <option id="0" value="0">Todos</option>
                    {foreach item=$source  from=$sources}
                        {if !empty($source['source']['name'])}
                            <option id="{$source['source']['reference']}" value="{$source['source']['reference']}">
                                {$source['source']['name']}</option>
                        {/if}
                    {/foreach}
                </select>
            </div>
            <div id="mbbxProductModalBody">
                {foreach from=$sources item=source }
                    {if !empty($source['source']['name'])}
                        <div id="{$source['source']['reference']}" class="mobbexSource">
                            <p class="mobbexPaymentMethod">
                                <img
                                    src="https://res.mobbex.com/images/sources/{$source['source']['reference']}.jpg">{$source['source']['name']}
                            </p>
                            {if !empty($source['installments']['list'])}
                                <table class="installmentsTable">
                                    {foreach from=$source['installments']['list'] item=installment }
                                        <tr>
                                            <td>{$installment['name']}</td>
                                            {if isset($installment['totals']['total'])}
                                                <td class="mbbxPlansPrice">${$installment['totals']['total']}</td>
                                            {else}
                                                <td></td>
                                            {/if}
                                        </tr>
                                    {/foreach}
                                </table>
                            {/if}
                        </div>
                    {/if}
                {/foreach}
            </div>
        </div>
    </div>

    <button type="button" id="mbbxProductBtn">{$style_settings['text']}
        {if !empty($style_settings['button_image'])}
            <img src="{$style_settings['button_image']}" width="40" height="40"
                style="margin-left: 15px; border-radius: 40px;">
        {/if}
    </button>

<<<<<<< HEAD
    <script>    
    
        // Get modal action buttons
        var body = document.querySelector('body')
        var openBtn = document.getElementById('mbbxProductBtn');
        var closeBtn = document.querySelector('#closembbxProduct');
        var mobbexPlansModal = document.querySelector('#mbbxProductModal');

        // Add events to toggle modal
        document.body.addEventListener('click', function(e) {
            if(e.target === openBtn || e.target === closeBtn || e.target === mbbxModalContainer) {
                mobbexPlansModal.classList.toggle('active');
                document.querySelector('body').classList.toggle('scroll-lock');
            } 
        });

        // Get sources and payment method selector 
        var sources = document.querySelectorAll('.mobbexSource');
        var methodSelect = document.getElementById('mbbx-method-select');

        // Filter payment methods in the modal
        methodSelect.addEventListener('change', function() {
            for (source of sources)
                source.style.display = source.id != methodSelect.value && methodSelect.value != 0 ? 'none' : '';
        });

=======
    <script>
        (function (window) {
            var cont  = document.querySelector('.mobbex-plans');
            var modal = document.querySelector('#mbbxProductModal');

            // Get modal action buttons
            var open  = document.querySelector('#mbbxProductBtn');
            var close = document.querySelector('#closembbxProduct');

            // Add events to toggle modal
            cont.addEventListener('click', function(e) {
                if (e.target === open || e.target === close || e.target === modal) {
                    modal.classList.toggle('active');
                    document.body.classList.toggle('scroll-lock');
                } 
            });

            // Get sources and payment method selector 
            var sources = document.querySelectorAll('.mobbexSource');
            var methodSelect = document.querySelector('#mbbx-method-select');

            // Filter payment methods in the modal
            methodSelect.addEventListener('change', function() {
                for (source of sources)
                    source.style.display = source.id != methodSelect.value && methodSelect.value != 0 ? 'none' : '';
            });
        }) (window);
>>>>>>> 3c4ad512213e11eb9a7fe5228ea97421be905bea
    </script>

</div>
