<table class="mbbx-plans-cont">
    <tbody>
        <tr style="text-align: center;">
            <td>{l s='Planes comunes' mod='mobbex'}</td>
            <td>{l s='Planes con reglas avanzadas' mod='mobbex'}</td>
        </tr>
        <tr>
            <td class="mbbx-plans">
                {foreach from=$plans['commonFields'] key=key item=field}
                    <div class="mbbx-plan">
                        <input type="hidden" name="{$field['id']}" value="no">
                        <input type="checkbox" name="{$field['id']}" value="yes" {if $field['value']}checked="checked" {/if}
                            id="{$field['id']}">
                        <label for="{$field['id']}">{$field['label']}</label>
                    </div>
                {/foreach}
            </td>
            <td class="mbbx-plans">
                {foreach from=$plans['advancedFields'] key=sourceRef item=fields}
                    <div class="mbbx-plan-source">
                        <img src="https://res.mobbex.com/images/sources/{$sourceRef}.png">
                        <p>{$plans['sourceNames'][$sourceRef]}</p>
                    </div>
                    {foreach from=$fields key=key item=field}
                        <div class="mbbx-plan-advanced">
                            <input type="checkbox" name="{$field['id']}" value="yes" {if $field['value']}checked="checked" {/if}
                                id="{$field['id']}">
                            <label for="{$field['id']}">{$field['label']}</label>
                        </div>
                    {/foreach}
                {/foreach}
            </td>
        </tr>
    </tbody>
</table>

<div class="mbbx-update-plans">
    <button class="btn btn-secondary mt-1" id="mbbx-update-btn">Actualizar medios de pago</button>
</div>

{literal}
    <style>
        .mbbx-plans-cont {
            border: 1px gainsboro solid;
            width: 500px;
        }

        .mbbx-plans-cont tbody {
            vertical-align: top
        }

        .mbbx-plans-cont td {
            width: 50%;
            border: 1px gainsboro solid;
            padding: 15px;
        }

        .mbbx-plans-cont label {
            font-weight: 400 !important;
        }

        .mbbx-plan-advanced {
            padding-left: 20px;
        }

        .mbbx-plan-source * {
            display: inline;
        }

        .mbbx-plan-source img {
            width: 30px;
            border-radius: 100%;
        }

        .mbbx-update-plans {
            margin: 20px 0;
        }

        .mbbx-update-plans p {
            margin-top: 10px;
        }
    </style>

    <script>
        (function(window) {

            var mbbxControllerUrl = {/literal}"{$update_sources}"{literal};

            function updateMbbxSources() {
                $('#mbbx-update-btn').toggleClass('disabled');
                $.ajax({
                    method: 'POST',
                    url: mbbxControllerUrl,
                    dataType: 'json',

                    success: function() {
                        window.top.location.reload();
                    },
                    error: () => {
                        window.top.location.reload();
                    }
                });
                return false;
            }

            $(document).on('click', '#mbbx-update-btn', function() {
                return updateMbbxSources();
            });

        })(window);
    </script>
{/literal}