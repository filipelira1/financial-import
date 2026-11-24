<?php

// Only the rules this API uses.
return [
    'after_or_equal' => 'O campo :attribute deve ser uma data igual ou posterior a :date.',
    'between' => [
        'numeric' => 'O campo :attribute deve estar entre :min e :max.',
    ],
    'date_format' => 'O campo :attribute deve estar no formato :format.',
    'email' => 'O campo :attribute deve ser um e-mail válido.',
    'enum' => 'O valor escolhido para :attribute é inválido.',
    'extensions' => 'O campo :attribute deve ter uma destas extensões: :values.',
    'file' => 'O campo :attribute deve ser um arquivo.',
    'integer' => 'O campo :attribute deve ser um número inteiro.',
    'max' => [
        'file' => 'O campo :attribute não pode ter mais que :max kilobytes.',
        'string' => 'O campo :attribute não pode ter mais que :max caracteres.',
    ],
    'mimetypes' => 'O campo :attribute deve ser um arquivo do tipo: :values.',
    'min' => [
        'numeric' => 'O campo :attribute deve ser pelo menos :min.',
    ],
    'required' => 'O campo :attribute é obrigatório.',
    'string' => 'O campo :attribute deve ser um texto.',
    'uploaded' => 'Não foi possível enviar o :attribute.',

    'attributes' => [
        'device_name' => 'nome do dispositivo',
        'email' => 'e-mail',
        'file' => 'arquivo',
        'from' => 'data inicial',
        'page' => 'página',
        'password' => 'senha',
        'per_page' => 'itens por página',
        'to' => 'data final',
        'type' => 'tipo',
    ],
];
