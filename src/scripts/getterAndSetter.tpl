

    public function get{{nameNormalizedUpper}}(): {{type}}
    {
        return $this->{{nameNormalized}};
    }

    public function set{{nameNormalizedUpper}}({{type}} ${{nameNormalized}}): self
    {
        $this->{{nameNormalized}} = ${{nameNormalized}};

        return $this;
    }