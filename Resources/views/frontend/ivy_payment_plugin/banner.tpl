<div
        class="ivy-banner"
        style="visibility: hidden;"
        {if $iviBannerType}data-variant="{$iviBannerType}"{/if}
        {if $unitPrice}data-unitprice="{$unitPrice|replace:',':'.'}"{/if}
        data-value="{$iviPrice|replace:',':'.'}"
        data-category="{$ivyMcc}"
        data-locale="{$ivyLocale}"
        data-shop-logo="{link file=$theme.desktopLogo}"
        data-banner-url="{$ivyBannerUrl}"
></div>
