<?php

namespace App\Stashcat\Responses;

use App\Stashcat\Entities\Company;

class CompanyMemberResponse extends DefaultResponse {

    /**
     * @return array
     * @throws \Exception
     */
    public function getCompanyIds(): array
    {
        $ids = [];
        foreach( $this->getResults("payload" , "companies") AS $key => $company ){
            $ids[] = $company["id"];
        }
        return $ids;
    }

    /**
     * @return array|Company[]
     * @throws \Exception
     */
    public function getCompanys(): array
    {
        $companies = [];
        foreach( $this->getResults('payload' , 'companies' ) AS $company ){
            $companies[ $company['id'] ] = new Company( $company );
        }
        return $companies;
    }

    /**
     * @param $name
     * @return mixed|null
     * @throws \Exception
     */
    public function getCompanyByName( $name ) : ?Company {
        foreach( $this->getCompanys() AS $company ){
            if( strtoupper( $company->getName() ) == strtoupper( $name ) ){
                return $company;
            }
        }
        return null;
    }

}