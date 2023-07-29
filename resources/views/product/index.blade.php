<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>

<body>
    <div style="display: flex; gap:  2rem">
        @foreach($products as $product)
        <div class="flex-1">
            <img src="{{ $product->image }}" style="max-width: 100%;">
            <h2>
                {{ $product->name }}
            </h2>
            <p>
                RM {{ $product->price }}
            </p>
        </div>
        @endforeach
    </div>
    <p>
        <form action="{{ route('checkout') }}" method="POST">
            @csrf
            <button>
                Checkout
            </button>
        </form>
    </p>
</body>

</html>