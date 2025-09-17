<?php $this->layout('layout', ['title' => 'Error']) ?>

<div class="max-w-md mx-auto">
    <div class="bg-white rounded-lg card-shadow p-8 text-center">
        <div class="text-6xl mb-4">⚠️</div>
        <h1 class="text-2xl font-bold text-gray-900 mb-4">Oops! Something went wrong</h1>
        <p class="text-gray-600 mb-6"><?= $this->e($message) ?></p>

        <div class="space-y-3">
            <a href="/" class="btn-primary block">Go Home</a>
            <button onclick="history.back()" class="btn-secondary block w-full">Go Back</button>
        </div>
    </div>
</div>