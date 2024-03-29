<table class="mbbx-plans-cont">
    <tbody>
        <tr style="text-align: center;">
            <td>{l s='Common Plans' mod='mobbex'}</td>
            <td>{l s='Advanced Plans' mod='mobbex'}</td>
        </tr>
        <tr>
            <td class="mbbx-plans">
                {foreach from=$plans['common'] key=key item=field}
                    <div class="mbbx-plan">
                        <div class="mbbx-plan-cont">
                            <div class="source-popover">
                                <h3>Medios Disponibles</h3>
                                {foreach from=$plans['groups'][$field['label']] item=sourceRef}
                                    <div class="mbbx-plan-group">
                                        <img src="https://res.mobbex.com/images/sources/original/{$sourceRef}.png">
                                        <p>
                                            {$sourceRef}
                                        </p>
                                    </div>
                                {/foreach}
                            </div>

                            <input type="hidden" name="{$field['id']}" value="no">
                            <input type="checkbox" name="{$field['id']}" value="yes" {if !in_array($field['key'], $check_common)}checked="checked" {/if}
                              id="{$field['id']}">
                            <label for="{$field['id']}">{$field['label']}</label>
                        </div>
                        <p>
                            {if isset($field['description'])}
                                <span>{{$field['description']}}</span>
                            {/if}
                        </p>
                    </div>
                {/foreach}
            </td>
            <td class="mbbx-plans">
                {foreach from=$plans['advanced'] key=sourceRef item=fields}
                    <div class="mbbx-plan-source">
                        <img src="https://res.mobbex.com/images/sources/{$sourceRef}.png">
                        <p>{$plans['names'][$sourceRef]}</p>
                    </div>
                    <div class="mbbx-plan">
                        {foreach from=$fields key=key item=field}
                            <div class="mbbx-plan-advanced">
                                <input type="checkbox" name="{$field['id']}" value="yes" {if in_array($field['key'], $check_advanced)}checked="checked" {/if}
                                    id="{$field['id']}">
                                <label for="{$field['id']}">{$field['label']}</label>
                                <p>
                                    {if isset($field['description'])}
                                        <span>{{$field['description']}}</span>
                                    {/if}
                                </p>
                            </div>
                        {/foreach}
                    </div>
                {/foreach}
            </td>
        </tr>
    </tbody>
</table>

<div class="mbbx-update-plans">
    <button class="btn btn-secondary mt-1" id="mbbx-update-btn">{l s='Update Plans' mod='mobbex'}</button>
</div>

{literal}
    <style>

        .source-popover {
            display: none;
            position: absolute;
            top: 2em;
            left: 30px;
            min-width: 15em;
            justify-content: center;
            flex-direction: column;
            border-radius: 5px;
            background-color: #eaeffb;
            padding: 1rem;
            z-index: 100;
        }

        .source-popover h3 {
            text-align: center;
        }

        .mbbx-plan {
            position: relative;
        }

        .mbbx-plan span {
            color: grey;
            font-size: .7rem;
        }

        .mbbx-plan:hover .source-popover {
            display: flex;
        }
        
        .mbbx-plan-group {
            display: flex;
            justify-content: left;
            align-items: center;
            padding: 5px;
            background-color: #eaeffb;
            margin-bottom: 15px;
        }

        .mbbx-plan-group img {
            width: 2.5em;
            margin: 0 5px;
        }

        .mbbx-plan-group p {
            margin: 0;
        }
        
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