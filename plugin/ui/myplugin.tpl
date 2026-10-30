{include file="sections/header.tpl"}
<link rel="stylesheet" href="{$myplugin_assets}css/myplugin.css">

<div class="row">
    <div class="col-md-8">
        <div class="panel panel-primary panel-hovered mb20">
            <div class="panel-heading">{Lang::T('My Plugin')}</div>
            <div class="panel-body">
                <table class="table table-bordered table-striped">
                    <thead><tr><th>#</th><th>{Lang::T('Title')}</th><th>{Lang::T('Created')}</th><th></th></tr></thead>
                    <tbody>
                        {foreach $items as $it}
                        <tr>
                            <td>{$it.id}</td>
                            <td>{$it.title|escape}</td>
                            <td>{Lang::dateTimeFormat($it.created_at)}</td>
                            <td class="text-right">
                                <form method="post" action="{Text::url('plugin/myplugin/delete/', $it.id)}" style="display:inline">
                                    <input type="hidden" name="csrf_token" value="{$csrf_token}">
                                    <button type="submit" class="btn btn-danger btn-xs"
                                        onclick="return confirm('{Lang::T('Delete?')}')">{Lang::T('Delete')}</button>
                                </form>
                            </td>
                        </tr>
                        {foreachelse}
                        <tr><td colspan="4" class="text-center text-muted">{Lang::T('Nothing here yet')}</td></tr>
                        {/foreach}
                    </tbody>
                </table>
                {* the Paginator in myplugin_overview() prepares this; it prints nothing when there is a single page *}
                {include file="pagination.tpl"}
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="panel panel-default mb20">
            <div class="panel-heading">{Lang::T('Add')}</div>
            <div class="panel-body">
                <form method="post" action="{Text::url('plugin/myplugin/add')}">
                    <input type="hidden" name="csrf_token" value="{$csrf_token}">
                    <div class="form-group">
                        <label>{Lang::T('Title')}</label>
                        <input type="text" name="title" class="form-control" required maxlength="255">
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">{Lang::T('Save')}</button>
                </form>
            </div>
        </div>
    </div>
</div>

{include file="sections/footer.tpl"}
