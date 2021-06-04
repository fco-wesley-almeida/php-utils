<?php

namespace App\Validations;


use App\Utils\DateUtils;

class Validation
{
    protected array $errors = [];
    
    /**
     * @return array:
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
    
    /**
     * @return int:
     */
    public function hasError(): bool
    {
        return !!$this->errors;
    }
    
    public function respondError (string $message = 'Informe os dados corretamente.'): void {
        echo json_encode([
            'message' => $message,
            'errors' => $this->errors,
        ]);
        http_response_code(400);
        header('Content-Type: application/json');
        exit;
    }
//
    protected function isInteger (string $errorMessage = 'O campo deve ser um número inteiro.'): callable {
        return function ($value) use ($errorMessage): string  {
            if ($value === null) {
                return '';
            }
            $str = (string)$value;
            $regex = '/^\d+$/';
            $testResult = preg_match($regex, $str);
            return $testResult 
                ? ''
                : $errorMessage;
        };
    }
    
    protected function isRequired (string $errorMessage = 'O campo é obrigatório.'): callable {
        return function ($value) use ($errorMessage): string  {
                return $value === null ? $errorMessage : '';
        };
    }
    
    protected function isNotEmpty (string $errorMessage = 'O campo não pode ser vazio.'): callable {
        return function ($value) use ($errorMessage): string  {
            if ($value === null) {
                return '';
            } else {
                return $value === '' ? $errorMessage : '';
            }
        };
    }
    
    protected function isValidDate (string $errorMessage = 'O campo deve ser uma data válida.'): callable {
        return function ($value) use ($errorMessage): string  {
            if ($value === null) {
                return '';
            } else {
                return DateUtils::isValidDate($value) ? '' : $errorMessage;
            }
        };
    }
    
    protected function isHourMinuteSecond (string $errorMessage = 'O campo deve ser um horário válido.'): callable {
        return function ($value) use ($errorMessage): string  {
            if ($value === null) {
                return '';
            } else {
                $isValid = preg_match('/\d{2}:\d{2}:\d{2}/', $value);
                return $isValid ? '' : $errorMessage;
            }
        };
    }
    
    protected function minLength (int $minNumChars, string $errorMessage = 'O campo não tem o número mínimo de caracteres.'): callable {
        return function ($value) use ($errorMessage, $minNumChars): string  {
            if ($value === null) {
                return '';
            }
            $isValid = strlen($value) >= $minNumChars;
            return $isValid ? '' : $errorMessage;
        };
    }
    
    protected function maxLength (int $maxNumChars, string $errorMessage = 'O campo superou o limite de caracteres.'): callable {
        return function ($value) use ($errorMessage, $maxNumChars): string  {
            if ($value === null) {
                return '';
            }
            $isValid = strlen($value) <= $maxNumChars;
            return $isValid ? '' : $errorMessage;
        };
    }
    
    public function validate (array $valuesArray, array $validations): void {
        $valuesArrayKeys = array_keys($valuesArray);
        foreach ($validations as $field => $fieldValidations):
            foreach ($fieldValidations as $fieldValidation):
                $valueToBeValidated = in_array($field, $valuesArrayKeys) ? $valuesArray[$field] : null;
                if (gettype($valueToBeValidated) === 'string'):
                    $valueToBeValidated = trim($valueToBeValidated);
                endif;
                $validationError = $fieldValidation($valueToBeValidated);
                if ($validationError):
                    $this->errors[$field] = utf8_encode($validationError);
                    break;
                endif;
            endforeach;
        endforeach;
        if ($this->hasError()) {
            $this->respondError();
        }
    }
}