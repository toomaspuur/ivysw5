{extends file="parent:frontend/detail/buy.tpl"}

{block name="frontend_detail_buy"}
    {$smarty.block.parent}
    {include file="frontend/ivy_payment_plugin/banner.tpl" iviBannerType='product' iviPrice=$sArticle.price unitPrice=$sArticle.price}
{/block}