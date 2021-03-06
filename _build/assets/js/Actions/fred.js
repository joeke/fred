import fredConfig from "../Config";
import fetch from "isomorphic-fetch";
import {errorHandler} from "../Utils";

export const getPreview = () => {
    return fetch(fredConfig.resource.previewUrl, {
        credentials: 'same-origin',
        headers: {
            'X-Fred-Token': fredConfig.jwt
        }
    }).then(response => {
        return response.text();
    })
};

export const saveContent = body => {
    if(!fredConfig.permission.save_document){
        return Promise.resolve(() => {
            throw new Error();
        });
    }
    
    return fetch(`${fredConfig.config.assetsUrl}endpoints/ajax.php?action=save-content`, {
        method: "post",
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'X-Fred-Token': fredConfig.jwt
        },
        body: JSON.stringify(body)
    }).then(errorHandler);
};

export const fetchContent = () => {
    return fetch(`${fredConfig.config.assetsUrl}endpoints/ajax.php?action=load-content&id=${fredConfig.resource.id}`, {
        credentials: 'same-origin',
        headers: {
            'X-Fred-Token': fredConfig.jwt
        }
    }).then(response => {
        return response.json();
    })
};

export const fetchLexicons = topics => {
    return fetch(`${fredConfig.config.assetsUrl}endpoints/ajax.php?action=load-lexicons${topics}`, {
        method: "get",
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'X-Fred-Token': fredConfig.jwt
        }
    }).then(response => {
        return response.json();
    });
};