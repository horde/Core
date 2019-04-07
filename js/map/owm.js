/**
 * OpenWeatherMap layer (See http://openweathermap.org/tile_map#list)
 *
 * Copyright 2015-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author Michael J. Rubinsky <mrubinsk@horde.org>
 *
 */
HordeMap.Owm = Class.create(
{
    initialize: function(opts)
    {
    },

    getLayers: function(layer)
    {
        return {
            'streets': new OpenLayers.Layer.OSM(
                'OpenStreetMap (Mapnik)',
                [
                    'http://a.tile.openstreetmap.org/${z}/${x}/${y}.png',
                    'http://b.tile.openstreetmap.org/${z}/${x}/${y}.png',
                    'http://c.tile.openstreetmap.org/${z}/${x}/${y}.png'
                ],
                { 'minZoomLevel': 1, 'numZoomLevels': 18 }
            ),
            'sat': new OpenLayers.Layer.XYZ(
                'OpenWeatherMap Satellite',
                    ['https://sat.owm.io/sql/${z}/${x}/${y}/?appid=' + HordeMap.conf['apikeys']['owm'] + '&overzoom=true&from=cloudless'],
                    {
                        'isBaseLayer': true,
                        'sphericalMercator': true,
                        'opacity': 1
                    }
            ),
            'clouds': new OpenLayers.Layer.XYZ(
                'OpenWeatherMap Cloud Map',
                    ['https://tile.openweathermap.org/map/clouds_new/${z}/${x}/${y}.png?appid=' + HordeMap.conf['apikeys']['owm']],
                    {
                        'isBaseLayer': false,
                        'sphericalMercator': true,
                        'opacity': 0.5
                    }
            ),
            'precipitation': new OpenLayers.Layer.XYZ(
                'OpenWeatherMap Precipitation Map',
                    ['https://tile.openweathermap.org/map/precipitation_new/${z}/${x}/${y}.png?appid=' + HordeMap.conf['apikeys']['owm']],
                    {
                        'isBaseLayer': false,
                        'sphericalMercator': true,
                        'opacity': 0.5
                    }
            ),
            'pressure_cntr': new OpenLayers.Layer.XYZ(
                'OpenWeatherMap Sea-Level Pressure Map',
                    ['https://tile.openweathermap.org/map/pressure_new/${z}/${x}/${y}.png?appid=' + HordeMap.conf['apikeys']['owm']],
                    {
                        'isBaseLayer': false,
                        'sphericalMercator': true,
                        'opacity': 0.5
                    }
            ),
            'wind': new OpenLayers.Layer.XYZ(
                'OpenWeatherMap Wind Map',
                    ['https://tile.openweathermap.org/map/wind_new/${z}/${x}/${y}.png?appid=' + HordeMap.conf['apikeys']['owm']],
                    {
                        'isBaseLayer': false,
                        'sphericalMercator': true,
                        'opacity': 0.5
                    }
            )
        };
    }
});
