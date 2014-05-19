<?php
/**
 *
 * Error Codes: 1701-1704
 */
namespace NibyNool\FitBitBundle\FitBit;

use NibyNool\FitBitBundle\FitBit\Exception as FBException;

/**
 * Class WaterGateway
 *
 * @package NibyNool\FitBitBundle\FitBit
 *
 * @since 0.1.0
 */
class WaterGateway extends EndpointGateway {

    /**
     * Get user water log entries for specific date
     *
     * @access public
     * @version 0.5.0
     *
     * @param  \DateTime $date
     * @throws FBException
     * @return mixed SimpleXMLElement or the value encoded in json as an object
     */
    public function getWater(\DateTime $date)
    {
        $dateStr = $date->format('Y-m-d');

        try
        {
	        return $this->makeApiRequest('user/-/foods/log/water/date/' . $dateStr);
        }
        catch (\Exception $e)
        {
	        throw new FBException('Could not get water records.', 1701, $e);
        }
    }

    /**
     * Log user water
     *
     * @access public
     * @version 0.5.0
     *
     * @todo Can this use a time in the date string?
     *
     * @param \DateTime $date Log entry date (set proper timezone, which could be fetched via getProfile)
     * @param string $amount Amount in ml/fl oz (as set with setMetric) or waterUnit
     * @param string $waterUnit Water Unit ("ml", "fl oz" or "cup")
     * @throws FBException
     * @return mixed SimpleXMLElement or the value encoded in json as an object
     */
    public function logWater(\DateTime $date, $amount, $waterUnit = null)
    {
        $parameters = array();
        $parameters['date'] = $date->format('Y-m-d');
        $parameters['amount'] = $amount;
        if (isset($waterUnit) && in_array($waterUnit, $this->configuration['water_units'][$waterUnit])) $parameters['unit'] = $waterUnit;
	    else throw new FBException('Invalid water unit provided.', 1702);

        try
        {
	        return $this->makeApiRequest('user/-/foods/log/water', 'POST', $parameters);
        }
        catch (\Exception $e)
        {
	        throw new FBException('Could not log water consumption.', 1703, $e);
        }
    }

    /**
     * Delete user water record
     *
     * @access public
     * @version 0.5.0
     *
     * @param string $id Water log id
     * @throws FBException
     * @return bool
     */
    public function deleteWater($id)
    {
        try
        {
	        return $this->makeApiRequest('user/-/foods/log/water/' . $id, 'DELETE');
        }
        catch (\Exception $e)
        {
	        throw new FBException('Could not delete water record.', 1704, $e);
        }
    }
}