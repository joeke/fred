<?php
/*
 * This file is part of the Fred package.
 *
 * Copyright (c) MODX, LLC
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * @property int $id
 * @property string $name
 * @property string $uuid
 * @property string $description
 * @property string $image
 * @property int $category
 * @property int $rank
 * @property int $option_set
 * @property array $options_override
 * @property string $content
 * 
 * @property FredElementCategory $Category
 * @property FredElementOptionSet $OptionSet
 * 
 * @package fred
 */
class FredElement extends xPDOSimpleObject {
    protected static $optionSetCache = [];
    
    public function processOptions()
    {
        $options = [];

        $optionSet = $this->get('option_set');
        if (!empty($optionSet)) {
            if (!isset(self::$optionSetCache[$optionSet])) {
                /** @var FredElementOptionSet $os */
                $os = $this->xpdo->getObject('FredElementOptionSet', $optionSet);
                
                if ($os) {
                    if ($os->get('complete') === true) {
                        self::$optionSetCache[$optionSet] = $os->processData();
                    }
                }
            }

            if (!empty(self::$optionSetCache[$optionSet])) {
                $options = self::$optionSetCache[$optionSet];
            }
        }

        $override = $this->get('options_override');
        if (empty($override)) $override = [];
        
        /** @var \FredElementOptionSet $tempOptionSet */
        $tempOptionSet = $this->xpdo->newObject('FredElementOptionSet');
        $tempOptionSet->set('data', $override);
        $override = $tempOptionSet->processData();
        
        if (isset($override['rteConfig']) && is_array($override['rteConfig']) && isset($options['rteConfig']) && is_array($options['rteConfig'])) {
            $override['rteConfig'] = array_merge($options['rteConfig'], $override['rteConfig']);
        }
        
        $options = array_merge($options, $override);
        
        if (isset($options['settings'])) {
            /** @var modX $modx */
            $modx = $this->xpdo;
            
            if (!$modx->user->get('sudo')) {
                $memberships = [];
                $groups = $modx->user->getUserGroups();
                $roles = [];

                if (!empty($groups)) {
                    /** @var modUserGroupMember[] $memberGroups */
                    $memberGroups = $modx->getIterator('modUserGroupMember', ['user_group:IN' => $groups, 'member' => $modx->user->id]);
                    foreach ($memberGroups as $memberGroup) {
                        $group = $memberGroup->getOne('UserGroup');
                        if (!$group) continue;

                        if (!isset($roles[$memberGroup->get('role')])) {
                            $role = $memberGroup->getOne('UserGroupRole');
                            if (!$role) continue;

                            $roles[$memberGroup->get('role')] = $role->get('authority');
                        }

                        $memberships[$group->get('name')] = $roles[$memberGroup->get('role')];
                    }
                }

                $rolesMap = [];
                /** @var modUserGroupRole[] $userGroupRoles */
                $userGroupRoles = $modx->getIterator('modUserGroupRole');
                foreach ($userGroupRoles as $userGroupRole) {
                    $rolesMap[$userGroupRole->get('name')] = $userGroupRole->get('authority');
                }

                $options['settings'] = $this->filterSettings($options['settings'], $memberships, $rolesMap);
            }
        }
        
        return $options;
    }
    
    private function filterSettings($settings, $memberships, $rolesMap)
    {
        $filtered = [];
        
        foreach ($settings as $setting) {
            $matchAll = (isset($setting['userGroupMatchAll'])) ? $setting['userGroupMatchAll'] : false;
            
            if (isset($setting['userGroup']) && is_array($setting['userGroup'])) {
                $match = false;
                
                foreach ($setting['userGroup'] as $userGroup) {
                    if (is_array($userGroup)) {
                        if (!isset($memberships[$userGroup['group']])) {
                            $match = false;

                            if ($matchAll === true) {
                                continue 2;
                            } else {
                                continue;
                            }
                        }
                        
                        if (isset($userGroup['role'])) {
                            if (!isset($rolesMap[$userGroup['role']])) continue 2;
                            
                            if ($memberships[$userGroup['group']] <= $rolesMap[$userGroup['role']]) {
                                $match = true;

                                if ($matchAll === false) {
                                    break;
                                }
                            } else {
                                $match = false;

                                if ($matchAll === true) {
                                    continue 2;
                                }
                            }
                        } else {
                            $match = true;
                            
                            if ($matchAll === false) {
                                break;
                            }
                        }
                    } else {
                        if (isset($memberships[$userGroup])) {
                            $match = true;
                            
                            if ($matchAll === false) {
                                break;
                            }
                        } else {
                            $match = false;

                            if ($matchAll === true) {
                                continue 2;
                            }
                        }
                    }
                }
                
                if ($match === false) continue;
            }
            
            if (isset($setting['group']) && !empty($setting['settings'])) {
                $setting['settings'] = $this->filterSettings($setting['settings'], $memberships, $rolesMap);
            }
            
            $filtered[] = $setting;
        }
        
        return $filtered;
    }

    public function getImage()
    {
        $image = 'https://via.placeholder.com/350x150?text=' . urlencode($this->name);

        if (!empty($this->image)) {
            $image = $this->image;

            $category = $this->Category;
            if ($category) {
                $theme = $category->Theme;
                if ($theme) {
                    $image = str_replace('{{theme_dir}}', $theme->getThemeFolderUri(), $image);
                }
            }
            
            $image = str_replace('{{assets_url}}', $this->xpdo->getOption('assets_url'), $image);

            if ((strtolower(substr($image, 0,7)) !== 'http://') && (strtolower(substr($image, 0,8)) !== 'https://') && (substr($image, 0,2) !== '//')  && (substr($image, 0,1) !== '/')) {
                $image = $this->xpdo->getOption('base_url') . $image;
            }
        }
        
        return $image;
    }

    public function save($cacheFlag = null)
    {
        $uuid = $this->get('uuid');

        if (empty($uuid)) {
            try {
                $this->set('uuid', \Fred\Utils::uuid());
            } catch (Exception $e) {}
        }
            
        return parent::save($cacheFlag);
    }

    public function getContent()
    {
        return $this->content;
    }
}