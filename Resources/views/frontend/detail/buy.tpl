{extends file="parent:frontend/detail/buy.tpl"}

{block name="frontend_detail_buy"}
    {$smarty.block.parent}
    {include file="frontend/ivy_payment_plugin/button.tpl"
        addDivClass='buybox--form'
        addButtonClass='buybox--button'
        iviPrice=$sArticle.price
        ivyAddToCart=true}
{/block}