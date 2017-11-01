<?php

namespace LasseRafn;

class PersonResponse
{
    const POS_TYPE = 0;
    const POS_PNR = 3;
    const POS_BIRTHDATE = 13;
    const POS_GENDER = 21;
    const POS_STATUS = 22;
    const POS_STATUS_DATE = 24;
    const POS_PNRGAELD = 37;
    const POS_DISEMPOWERMENT = 46;
    const POS_ADDRESSDATE = 58;
    const POS_NAMEPROTECTIONDATE = 70;
    const POS_POSITION = 82;
    const POS_ADDRESSNAME = 116;
    const POS_CONAME = 150;
    const POS_LOCALITY = 184;
    const POS_STREET = 218;
    const POS_CITY = 252;
    const POS_ZIPCODE = 286;
    const POS_POSTDISTRICT = 290;
    const POS_STATE = 310;
    const POS_STREETCODE = 314;
    const POS_STREET_NUMBER = 318;
    const POS_FLOOR = 322;
    const POS_SIDE_NUMBER = 324;
    const POS_BNR = 328;
    const POS_FIRSTNAME = 332;
    const POS_LASTNAME = 382;
    const POS_STREETADDRESS_NAME = 422;

    const LENGTH_TYPE = 3;
    const LENGTH_PNR = 10;
    const LENGTH_BIRTHDATE = 8;
    const LENGTH_GENDER = 1;
    const LENGTH_STATUS = 2;
    const LENGTH_STATUS_DATE = 12;
    const LENGTH_PNRGAELD = 10;
    const LENGTH_DISEMPOWERMENT = 12;
    const LENGTH_ADDRESSDATE = 12;
    const LENGTH_NAMEPROTECTIONDATE = 12;
    const LENGTH_POSITION = 34;
    const LENGTH_ADDRESSNAME = 34;
    const LENGTH_CONAME = 34;
    const LENGTH_LOCALITY = 34;
    const LENGTH_STREET = 34;
    const LENGTH_CITY = 34;
    const LENGTH_ZIPCODE = 4;
    const LENGTH_POSTDISTRICT = 20;
    const LENGTH_STATE = 4;
    const LENGTH_STREETCODE = 4;
    const LENGTH_STREET_NUMBER = 4;
    const LENGTH_FLOOR = 2;
    const LENGTH_SIDE_NUMBER = 4;
    const LENGTH_BNR = 4;
    const LENGTH_FIRSTNAME = 50;
    const LENGTH_LASTNAME = 40;
    const LENGTH_STREETADDRESS_NAME = 20;

    public $type;
    public $pnr;
    public $birthdate;
    public $gender;
    public $status;
    public $status_date;
    public $pnrgaeld;
    public $disempowerment;
    public $addressdate;
    public $nameprotectiondate;
    public $position;
    public $addressname;
    public $coname;
    public $locality;
    public $street;
    public $city;
    public $zipcode;
    public $postdistrict;
    public $state;
    public $streetcode;
    public $street_number;
    public $floor;
    public $side_number;
    public $bnr;
    public $firstname;
    public $lastname;
    public $streetaddress_name;

    public function __construct($start, $response)
    {
        $this->parseResponse($start, $response);
    }

    public function isDeceased()
    {
        return ((int) $this->status) === 90;
    }

    public function isLost()
    {
        return ((int) $this->status) === 70;
    }

    public function isRegularActive()
    {
        return ((int) $this->status) === 1;
    }

    private function parseResponse($start, $response)
    {
	    $response = utf8_encode($response);
        $this->type = trim(substr($response, $start + static::POS_TYPE, static::LENGTH_TYPE));
        $this->pnr = trim(substr($response, $start + static::POS_PNR, static::LENGTH_PNR));
        $this->birthdate = trim(substr($response, $start + static::POS_BIRTHDATE, static::LENGTH_BIRTHDATE));
        $this->gender = trim(substr($response, $start + static::POS_GENDER, static::LENGTH_GENDER));
        $this->status = trim(substr($response, $start + static::POS_STATUS, static::LENGTH_STATUS));
        $this->status_date = trim(substr($response, $start + static::POS_STATUS_DATE, static::LENGTH_STATUS_DATE));
        $this->pnrgaeld = trim(substr($response, $start + static::POS_PNRGAELD, static::LENGTH_PNRGAELD));
        $this->disempowerment = trim(substr($response, $start + static::POS_DISEMPOWERMENT, static::LENGTH_DISEMPOWERMENT));
        $this->addressdate = trim(substr($response, $start + static::POS_ADDRESSDATE, static::LENGTH_ADDRESSDATE));
        $this->nameprotectiondate = trim(substr($response, $start + static::POS_NAMEPROTECTIONDATE, static::LENGTH_NAMEPROTECTIONDATE));
        $this->position = trim(substr($response, $start + static::POS_POSITION, static::LENGTH_POSITION));
        $this->addressname = trim(substr($response, $start + static::POS_ADDRESSNAME, static::LENGTH_ADDRESSNAME));
        $this->coname = trim(substr($response, $start + static::POS_CONAME, static::LENGTH_CONAME));
        $this->locality = trim(substr($response, $start + static::POS_LOCALITY, static::LENGTH_LOCALITY));
        $this->street = trim(substr($response, $start + static::POS_STREET, static::LENGTH_STREET));
        $this->city = trim(substr($response, $start + static::POS_CITY, static::LENGTH_CITY));
        $this->zipcode = trim(substr($response, $start + static::POS_ZIPCODE, static::LENGTH_ZIPCODE));
        $this->postdistrict = trim(substr($response, $start + static::POS_POSTDISTRICT, static::LENGTH_POSTDISTRICT));
        $this->state = trim(substr($response, $start + static::POS_STATE, static::LENGTH_STATE));
        $this->streetcode = trim(substr($response, $start + static::POS_STREETCODE, static::LENGTH_STREETCODE));
        $this->street_number = trim(substr($response, $start + static::POS_STREET_NUMBER, static::LENGTH_STREET_NUMBER));
        $this->floor = trim(substr($response, $start + static::POS_FLOOR, static::LENGTH_FLOOR));
        $this->side_number = trim(substr($response, $start + static::POS_SIDE_NUMBER, static::LENGTH_SIDE_NUMBER));
        $this->bnr = trim(substr($response, $start + static::POS_BNR, static::LENGTH_BNR));
        $this->firstname = trim(substr($response, $start + static::POS_FIRSTNAME, static::LENGTH_FIRSTNAME));
        $this->lastname = trim(substr($response, $start + static::POS_LASTNAME, static::LENGTH_LASTNAME));
        $this->streetaddress_name = trim(substr($response, $start + static::POS_STREETADDRESS_NAME, static::LENGTH_STREETADDRESS_NAME));
    }
}
