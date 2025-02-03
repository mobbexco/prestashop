<div id="mbbxWidgetContainer">
    {* Finance Widget Container *}
    <div id="mbbxFinanceWidget"></div>

    {* Button Styles *}
    <style>
        {$styles}
    </style>

    {* General Styles *}
    <link rel="stylesheet" href="{$styles_url}">

    {* Script data *}
    <script>
        const mobbexWidget = {
            type: '{$type|escape:'javascript'}',
            sourcesUrl: '{$sourcesUrl|escape:'javascript' nofilter}',
            updateUrl: '{$updateUrl|escape:'javascript' nofilter}',
            price: {$price|escape:'javascript'},
            text: '{$text|escape:'javascript'}',
            logo: '{$logo|escape:'javascript'}',
            theme: '{$theme|escape:'javascript'}',
            product_ids: {$product_ids|json_encode nofilter}
        };
    </script>

    {* main script *}
    <script src="{$scriptUrl}" defer></script>
</div>