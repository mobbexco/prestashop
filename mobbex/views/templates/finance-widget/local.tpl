<div id="mbbxWidgetContainer">
    {* Finance Widget Container *}
    <div id="mbbxFinanceWidget"></div>


    {* Script data *}
    <script>
        ((window) => {
            window.mbbxFinanceWidget = {
                sources: {$sources|json_encode nofilter},
                theme: "{$theme|escape:'javascript'}",
                root: "mbbxFinanceWidget"
            };
        })(window);
    </script>
    <script src='{$library}' type='module'></script>


</div>