{include file="sections/user-header.tpl"}

<div class="panel panel-primary panel-hovered mb20">
    <div class="panel-heading">{Lang::T('My Plugin')}</div>
    <div class="panel-body">
        <p>{$greeting|escape}, {$_user.fullname|escape}!</p>
    </div>
</div>

{include file="sections/user-footer.tpl"}
