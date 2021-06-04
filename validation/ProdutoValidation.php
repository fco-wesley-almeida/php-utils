<?php

namespace App\Validations;


class ProdutoValidation extends Validation
{
    public function validateRegister (array $dataPost): void {
        $validations = [
            'tipo_produto_id'   => [$this->isRequired(), $this->isInteger()],
            'clinica_id'        => [$this->isRequired(), $this->isInteger()],
            'preco'             => [$this->isRequired()], // TODO: validar se é decimal,
            'nome'              => [$this->isRequired(), $this->isNotEmpty()]
        ];
        $this->validate($dataPost, $validations);
    }
}
