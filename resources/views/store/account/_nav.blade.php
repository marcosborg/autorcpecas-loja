<div class="list-group account-nav-group mb-3">
    <a href="{{ url('/loja/conta') }}" class="list-group-item list-group-item-action @if(request()->is('loja/conta')) active @endif">Resumo</a>
    <a href="{{ url('/loja/conta/moradas') }}" class="list-group-item list-group-item-action @if(request()->is('loja/conta/moradas*')) active @endif">Moradas</a>
    <a href="{{ url('/loja/conta/encomendas') }}" class="list-group-item list-group-item-action @if(request()->is('loja/conta/encomendas*')) active @endif">Encomendas</a>
    <a href="{{ url('/loja/carrinho') }}" class="list-group-item list-group-item-action @if(request()->is('loja/carrinho*')) active @endif">Carrinho</a>
    <form method="post" action="{{ url('/loja/conta/logout') }}">
        @csrf
        <button type="submit" class="list-group-item list-group-item-action text-start">Terminar sessao</button>
    </form>
</div>
