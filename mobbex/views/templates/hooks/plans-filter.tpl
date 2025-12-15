<div id="mbbx-plans-configurator" style="display:flex"></div>
<script>
    window.mbbx = {$mbbx|json_encode nofilter};
</script>
<script>
    {literal}
        console.log("mbbx", mbbx);
        window.platformFormName = "";
        window.mobbexSources = mbbx.filtered_plans;
        window.mobbexFeaturedPlans = mbbx.featured_plans;
        window.mobbexAdvancedPlans = mbbx.advanced_plans;
        window.mobbexManual = mbbx.manual_config === "yes";
        window.mobbexShowFeaturedPlans = mbbx.show_featured === "yes";
    {/literal}
</script>
<script type='text/javascript' src='{$mediaPath}/views/js/plans-configurator.min.js'></script>